<?php
namespace Tests\Unit\Services;
use App\Dto\InventoryHistory\InventoryHistoryEntry;
use App\Dto\InventoryHistory\UnpersistedInventoryHistoryEntry;
use App\Dto\Order\Order;
use App\Dto\Order\UnpersistedOrder;
use App\Dto\Order\UnpersistedOrderItem;
use App\Enums\OrderStatus;
use App\Events\OrderCreatedEvent;
use App\Events\OrderPaidEvent;
use App\Events\OrderShippedEvent;
use App\Exceptions\InsufficientStockException;
use App\Exceptions\InvalidOrderStateException;
use App\Exceptions\OrderNotFoundException;
use App\Exceptions\ProductNotFoundException;
use App\Models\InventoryHistory\InventoryHistoryModel;
use App\Models\Order\OrderModel;
use App\Models\Product\ProductModel;
use App\Models\UserModel;
use App\Repositories\InventoryHistory\InventoryHistoryRepository;
use App\Repositories\Order\OrderRepository;
use App\Repositories\Product\ProductRepository;
use App\Services\AuditLogger;
use App\Services\Order\OrderService;
use App\Services\Order\OrderStatusMachine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\TestCase;
class OrderServiceTest extends TestCase
{
    use RefreshDatabase;
    /** @var OrderRepository&MockObject */
    private OrderRepository $repository;
    /** @var ProductRepository&MockObject */
    private ProductRepository $productRepository;
    /** @var InventoryHistoryRepository&MockObject */
    private InventoryHistoryRepository $inventoryHistoryRepository;
    /** @var OrderStatusMachine&MockObject */
    private OrderStatusMachine $statusMachine;
    private OrderService $service;
    protected function setUp(): void
    {
        parent::setUp();
        $this->repository                 = $this->createMock(OrderRepository::class);
        $this->productRepository          = $this->createMock(ProductRepository::class);
        $this->inventoryHistoryRepository = $this->createMock(InventoryHistoryRepository::class);
        $this->statusMachine              = $this->createMock(OrderStatusMachine::class);
        $this->service = new OrderService(
            $this->repository,
            $this->productRepository,
            $this->inventoryHistoryRepository,
            $this->statusMachine,
            new AuditLogger(),
        );
    }
    public function test_listOrders_returns_paginator_of_orders(): void
    {
        $orderModel = OrderModel::factory()->create();
        $order      = Order::fromModel($orderModel);
        $paginator = \Mockery::mock(LengthAwarePaginator::class);
        $paginator->shouldReceive('items')->andReturn([$order]);
        $this->repository
            ->expects($this->once())
            ->method('getAll')
            ->with(1, 15, 'id', 'asc', [], [])
            ->willReturn($paginator);
        $this->service->listOrders(1, 15, 'id', 'asc', [], []);
    }
    public function test_getOrderById_returns_order(): void
    {
        $id    = 1;
        $order = Order::fromModel(OrderModel::factory()->create());
        $this->repository
            ->expects($this->once())
            ->method('findById')
            ->with($id)
            ->willReturn($order);
        $result = $this->service->getOrderById($id);
        $this->assertSame($order, $result);
    }
    public function test_getOrderById_returns_null(): void
    {
        $id = 1;
        $this->repository
            ->expects($this->once())
            ->method('findById')
            ->with($id)
            ->willReturn(null);
        $result = $this->service->getOrderById($id);
        $this->assertNull($result);
    }
    public function test_createOrder_deducts_stock_and_records_inventory_history(): void
    {
        $user = UserModel::factory()->create();
        /** @var ProductModel $productModel */
        $productModel = ProductModel::factory()->create(['quantity' => 10]);
        $item = new UnpersistedOrderItem(
            productId: $productModel->id,
            quantity:  3,
            unitPrice: 50.0,
        );
        $unpersisted = new UnpersistedOrder(
            userId:     $user->id,
            status:     OrderStatus::Pending,
            totalPrice: 150,
            items:      [$item],
        );
        $persisted = Order::fromModel(OrderModel::factory()->create());
        $this->productRepository
            ->expects($this->once())
            ->method('findByIdForUpdate')
            ->with($productModel->id)
            ->willReturn($productModel);
        $this->inventoryHistoryRepository
            ->expects($this->once())
            ->method('record')
            ->with($this->callback(function (UnpersistedInventoryHistoryEntry $entry) use ($productModel) {
                return $entry->productId       === $productModel->id
                    && $entry->changeType      === 'sale'
                    && $entry->previousQuantity === 10
                    && $entry->newQuantity      === 7
                    && $entry->quantityChanged  === -3;
            }))
            ->willReturn(InventoryHistoryEntry::fromModel(InventoryHistoryModel::factory()->create()));
        $this->repository
            ->expects($this->once())
            ->method('persist')
            ->with($unpersisted)
            ->willReturn($persisted);
        $result = $this->service->createOrder($unpersisted);
        $this->assertSame($persisted, $result);
    }
    public function test_createOrder_throws_InsufficientStockException_when_stock_too_low(): void
    {
        $user = UserModel::factory()->create();
        /** @var ProductModel $productModel */
        $productModel = ProductModel::factory()->create(['quantity' => 2]);
        $item = new UnpersistedOrderItem(
            productId: $productModel->id,
            quantity:  5,
            unitPrice: 50.0,
        );
        $unpersisted = new UnpersistedOrder(
            userId:     $user->id,
            status:     OrderStatus::Pending,
            totalPrice: 250,
            items:      [$item],
        );
        $this->productRepository
            ->expects($this->once())
            ->method('findByIdForUpdate')
            ->with($productModel->id)
            ->willReturn($productModel);
        $this->repository
            ->expects($this->never())
            ->method('persist');
        $this->expectException(InsufficientStockException::class);
        $this->service->createOrder($unpersisted);
    }
    public function test_createOrder_throws_ProductNotFoundException_when_product_missing(): void
    {
        $user = UserModel::factory()->create();
        $item = new UnpersistedOrderItem(
            productId: 9999,
            quantity:  1,
            unitPrice: 50.0,
        );
        $unpersisted = new UnpersistedOrder(
            userId:     $user->id,
            status:     OrderStatus::Pending,
            totalPrice: 50,
            items:      [$item],
        );
        $this->productRepository
            ->expects($this->once())
            ->method('findByIdForUpdate')
            ->with(9999)
            ->willReturn(null);
        $this->repository
            ->expects($this->never())
            ->method('persist');
        $this->expectException(ProductNotFoundException::class);
        $this->service->createOrder($unpersisted);
    }
    public function test_createOrder_dispatches_order_created_event(): void
    {
        Event::fake([OrderCreatedEvent::class]);
        $user = UserModel::factory()->create();
        /** @var ProductModel $productModel */
        $productModel = ProductModel::factory()->create(['quantity' => 10]);
        $item = new UnpersistedOrderItem(
            productId: $productModel->id,
            quantity: 1,
            unitPrice: 50.0,
        );
        $unpersisted = new UnpersistedOrder(
            userId: $user->id,
            status: OrderStatus::Pending,
            totalPrice: 50,
            items: [$item],
        );
        $persisted = Order::fromModel(OrderModel::factory()->create());
        $this->productRepository->method('findByIdForUpdate')->willReturn($productModel);
        $this->inventoryHistoryRepository->method('record')
            ->willReturn(InventoryHistoryEntry::fromModel(InventoryHistoryModel::factory()->create()));
        $this->repository->method('persist')->willReturn($persisted);
        $this->service->createOrder($unpersisted);
        Event::assertDispatched(OrderCreatedEvent::class, function (OrderCreatedEvent $event) use ($persisted) {
            return $event->order->id === $persisted->id;
        });
    }
    public function test_updateOrder_delegates_to_user_status_machine(): void
    {
        $id   = 1;
        $user = UserModel::factory()->create();
        /** @var OrderModel $orderModel */
        $orderModel    = OrderModel::factory()->create(['status' => OrderStatus::Pending->value]);
        $existingOrder = Order::fromModel($orderModel);
        $unpersisted = new UnpersistedOrder(
            userId:     $user->id,
            status:     OrderStatus::Cancelled,
            totalPrice: 200,
            items:      [],
        );
        $updated = Order::fromModel(OrderModel::factory()->create());
        $this->repository
            ->expects($this->once())
            ->method('findById')
            ->with($id)
            ->willReturn($existingOrder);
        $this->statusMachine
            ->expects($this->once())
            ->method('assertUserTransitionAllowed')
            ->with($existingOrder, OrderStatus::Cancelled);
        $this->statusMachine
            ->expects($this->never())
            ->method('assertAdminTransitionAllowed');
        $this->repository
            ->expects($this->once())
            ->method('update')
            ->with($id, $unpersisted)
            ->willReturn($updated);
        $result = $this->service->updateOrder($id, $unpersisted);
        $this->assertSame($updated, $result);
    }
    public function test_updateOrder_throws_OrderNotFoundException(): void
    {
        $id   = 1;
        $user = UserModel::factory()->create();
        $unpersisted = new UnpersistedOrder(
            userId:     $user->id,
            status:     OrderStatus::Cancelled,
            totalPrice: 200,
            items:      [],
        );
        $this->repository
            ->expects($this->once())
            ->method('findById')
            ->with($id)
            ->willReturn(null);
        $this->repository
            ->expects($this->never())
            ->method('update');
        $this->expectException(OrderNotFoundException::class);
        $this->service->updateOrder($id, $unpersisted);
    }
    public function test_updateOrder_delegates_to_admin_status_machine_for_admin(): void
    {
        $id   = 1;
        $user = UserModel::factory()->create();
        /** @var OrderModel $orderModel */
        $orderModel    = OrderModel::factory()->create(['status' => OrderStatus::Pending->value]);
        $existingOrder = Order::fromModel($orderModel);
        $unpersisted = new UnpersistedOrder(
            userId:     $user->id,
            status:     OrderStatus::Paid,
            totalPrice: 200,
            items:      [],
        );
        $updated = Order::fromModel(OrderModel::factory()->create());
        $this->repository
            ->expects($this->once())
            ->method('findById')
            ->with($id)
            ->willReturn($existingOrder);
        $this->statusMachine
            ->expects($this->once())
            ->method('assertAdminTransitionAllowed')
            ->with($existingOrder, OrderStatus::Paid);
        $this->statusMachine
            ->expects($this->never())
            ->method('assertUserTransitionAllowed');
        $this->repository
            ->expects($this->once())
            ->method('update')
            ->with($id, $unpersisted)
            ->willReturn($updated);
        $result = $this->service->updateOrder($id, $unpersisted, asAdmin: true);
        $this->assertSame($updated, $result);
    }
    public function test_markOrderAsPaid_transitions_pending_order_and_dispatches_event(): void
    {
        Event::fake([OrderPaidEvent::class]);
        $user = UserModel::factory()->create();
        /** @var OrderModel $orderModel */
        $orderModel = OrderModel::factory()->create([
            'status' => OrderStatus::Pending->value,
            'user_id' => $user->id,
            'total_price' => 200,
        ]);
        $existingOrder = Order::fromModel($orderModel);
        $updatedModel = OrderModel::factory()->create([
            'status' => OrderStatus::Paid->value,
            'user_id' => $user->id,
            'total_price' => 200,
        ]);
        $updated = Order::fromModel($updatedModel);
        $this->repository->method('findById')->willReturn($existingOrder);
        $this->repository->method('update')->willReturn($updated);
        $result = $this->service->markOrderAsPaid(1, 'pay_ref_123');
        $this->assertSame($updated, $result);
        Event::assertDispatched(OrderPaidEvent::class, function (OrderPaidEvent $event) use ($updated) {
            return $event->order->id === $updated->id;
        });
    }
    public function test_markOrderAsPaid_throws_when_order_not_pending(): void
    {
        $user = UserModel::factory()->create();
        /** @var OrderModel $orderModel */
        $orderModel = OrderModel::factory()->create([
            'status' => OrderStatus::Shipped->value,
            'user_id' => $user->id,
        ]);
        $existingOrder = Order::fromModel($orderModel);
        $this->repository->method('findById')->willReturn($existingOrder);
        $this->expectException(InvalidOrderStateException::class);
        $this->service->markOrderAsPaid(1, 'pay_ref_123');
    }
    public function test_markOrderAsPaid_throws_when_order_not_found(): void
    {
        $this->repository->method('findById')->willReturn(null);
        $this->expectException(OrderNotFoundException::class);
        $this->service->markOrderAsPaid(999, 'pay_ref_123');
    }
    public function test_updateOrder_does_not_dispatch_paid_event(): void
    {
        Event::fake([OrderPaidEvent::class]);
        $user = UserModel::factory()->create();
        /** @var OrderModel $orderModel */
        $orderModel = OrderModel::factory()->create(['status' => OrderStatus::Pending->value]);
        $existingOrder = Order::fromModel($orderModel);
        $unpersisted = new UnpersistedOrder(
            userId: $user->id,
            status: OrderStatus::Cancelled,
            totalPrice: 200,
            items: [],
        );
        $updated = Order::fromModel(OrderModel::factory()->create(['status' => OrderStatus::Cancelled->value]));
        $this->repository->method('findById')->willReturn($existingOrder);
        $this->repository->method('update')->willReturn($updated);
        $this->service->updateOrder(1, $unpersisted);
        Event::assertNotDispatched(OrderPaidEvent::class);
    }
    public function test_updateOrder_dispatches_shipped_event_on_shipped_transition(): void
    {
        Event::fake([OrderShippedEvent::class]);
        $user = UserModel::factory()->create();
        /** @var OrderModel $orderModel */
        $orderModel = OrderModel::factory()->create(['status' => OrderStatus::Paid->value]);
        $existingOrder = Order::fromModel($orderModel);
        $unpersisted = new UnpersistedOrder(
            userId: $user->id,
            status: OrderStatus::Shipped,
            totalPrice: 200,
            items: [],
        );
        $updated = Order::fromModel(OrderModel::factory()->create(['status' => OrderStatus::Shipped->value]));
        $this->repository->method('findById')->willReturn($existingOrder);
        $this->repository->method('update')->willReturn($updated);
        $this->service->updateOrder(1, $unpersisted, asAdmin: true);
        Event::assertDispatched(OrderShippedEvent::class, function (OrderShippedEvent $event) use ($updated) {
            return $event->order->id === $updated->id;
        });
    }
    public function test_updateOrder_does_not_dispatch_shipped_event_on_non_shipped_transition(): void
    {
        Event::fake([OrderShippedEvent::class]);
        $user = UserModel::factory()->create();
        /** @var OrderModel $orderModel */
        $orderModel = OrderModel::factory()->create(['status' => OrderStatus::Pending->value]);
        $existingOrder = Order::fromModel($orderModel);
        $unpersisted = new UnpersistedOrder(
            userId: $user->id,
            status: OrderStatus::Cancelled,
            totalPrice: 200,
            items: [],
        );
        $updated = Order::fromModel(OrderModel::factory()->create(['status' => OrderStatus::Cancelled->value]));
        $this->repository->method('findById')->willReturn($existingOrder);
        $this->repository->method('update')->willReturn($updated);
        $this->service->updateOrder(1, $unpersisted);
        Event::assertNotDispatched(OrderShippedEvent::class);
    }
    public function test_deleteOrder_returns_true(): void
    {
        $id = 1;
        $this->repository
            ->expects($this->once())
            ->method('delete')
            ->with($id)
            ->willReturn(true);
        $result = $this->service->deleteOrder($id);
        $this->assertTrue($result);
    }
    public function test_deleteOrder_throws_OrderNotFoundException(): void
    {
        $id = 1;
        $this->repository
            ->expects($this->once())
            ->method('delete')
            ->with($id)
            ->willThrowException(new OrderNotFoundException($id));
        $this->expectException(OrderNotFoundException::class);
        $this->service->deleteOrder($id);
    }
}
