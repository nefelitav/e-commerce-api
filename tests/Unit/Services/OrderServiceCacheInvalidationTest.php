<?php

namespace Tests\Unit\Services;

use App\Dto\InventoryHistory\InventoryHistoryEntry;
use App\Dto\InventoryHistory\UnpersistedInventoryHistoryEntry;
use App\Dto\Order\Order;
use App\Dto\Order\UnpersistedOrder;
use App\Dto\Order\UnpersistedOrderItem;
use App\Enums\OrderStatus;
use App\Exceptions\InsufficientStockException;
use App\Exceptions\ProductNotFoundException;
use App\Models\InventoryHistory\InventoryHistoryModel;
use App\Models\Order\OrderModel;
use App\Models\Product\ProductModel;
use App\Models\UserModel;
use App\Repositories\InventoryHistory\InventoryHistoryRepository;
use App\Repositories\Order\OrderRepository;
use App\Repositories\Product\ProductRepository;
use App\Services\Order\OrderService;
use App\Services\Order\OrderStatusMachine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\TestCase;

class OrderServiceCacheInvalidationTest extends TestCase
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

        Cache::tags(['products'])->flush();
        $this->repository = $this->createMock(OrderRepository::class);
        $this->productRepository = $this->createMock(ProductRepository::class);
        $this->inventoryHistoryRepository = $this->createMock(InventoryHistoryRepository::class);
        $this->statusMachine = $this->createMock(OrderStatusMachine::class);
        $this->service = new OrderService(
            $this->repository,
            $this->productRepository,
            $this->inventoryHistoryRepository,
            $this->statusMachine,
        );
    }

    protected function tearDown(): void
    {
        Cache::tags(['products'])->flush();
        parent::tearDown();
    }

    public function test_createOrder_flushes_product_cache_for_all_ordered_products(): void
    {
        $user = UserModel::factory()->create();

        /** @var ProductModel $product1 */
        $product1 = ProductModel::factory()->create(['quantity' => 10]);
        /** @var ProductModel $product2 */
        $product2 = ProductModel::factory()->create(['quantity' => 20]);

        Cache::tags(['products'])->put("products.{$product1->id}", 'cached_value', 300);
        Cache::tags(['products'])->put("products.{$product2->id}", 'cached_value', 300);
        Cache::tags(['products'])->put('products.all.test', 'cached_value', 300);

        $item1 = new UnpersistedOrderItem(
            productId: $product1->id,
            quantity: 2,
            unitPrice: 50.0,
        );

        $item2 = new UnpersistedOrderItem(
            productId: $product2->id,
            quantity: 3,
            unitPrice: 30.0,
        );

        $unpersisted = new UnpersistedOrder(
            userId: $user->id,
            status: OrderStatus::Pending,
            totalPrice: 190,
            items: [$item1, $item2],
        );

        $persisted = Order::fromModel(OrderModel::factory()->create());

        $this->productRepository
            ->method('findByIdForUpdate')
            ->willReturnMap([
                [$product1->id, $product1],
                [$product2->id, $product2],
            ]);

        $this->inventoryHistoryRepository
            ->method('record')
            ->willReturn(InventoryHistoryEntry::fromModel(InventoryHistoryModel::factory()->create()));

        $this->repository->method('persist')->willReturn($persisted);

        $this->service->createOrder($unpersisted);

        $this->assertFalse(Cache::tags(['products'])->has("products.{$product1->id}"));
        $this->assertFalse(Cache::tags(['products'])->has("products.{$product2->id}"));
        $this->assertFalse(Cache::tags(['products'])->has('products.all.test'));
    }

    public function test_createOrder_does_not_flush_cache_when_product_not_found(): void
    {
        $user = UserModel::factory()->create();

        Cache::tags(['products'])->put('products.all.test', 'cached_value', 300);

        $item = new UnpersistedOrderItem(
            productId: 9999,
            quantity: 1,
            unitPrice: 50.0,
        );

        $unpersisted = new UnpersistedOrder(
            userId: $user->id,
            status: OrderStatus::Pending,
            totalPrice: 50,
            items: [$item],
        );

        $this->productRepository
            ->method('findByIdForUpdate')
            ->willReturn(null);

        try {
            $this->service->createOrder($unpersisted);
        } catch (ProductNotFoundException) {
        }

        $this->assertTrue(Cache::tags(['products'])->has('products.all.test'));
    }

    public function test_createOrder_does_not_flush_cache_when_insufficient_stock(): void
    {
        $user = UserModel::factory()->create();

        /** @var ProductModel $product */
        $product = ProductModel::factory()->create(['quantity' => 2]);

        Cache::tags(['products'])->put("products.{$product->id}", 'cached_value', 300);
        Cache::tags(['products'])->put('products.all.test', 'cached_value', 300);

        $item = new UnpersistedOrderItem(
            productId: $product->id,
            quantity: 5,
            unitPrice: 50.0,
        );

        $unpersisted = new UnpersistedOrder(
            userId: $user->id,
            status: OrderStatus::Pending,
            totalPrice: 250,
            items: [$item],
        );

        $this->productRepository
            ->method('findByIdForUpdate')
            ->willReturn($product);

        try {
            $this->service->createOrder($unpersisted);
        } catch (InsufficientStockException) {
        }

        $this->assertTrue(Cache::tags(['products'])->has("products.{$product->id}"));
        $this->assertTrue(Cache::tags(['products'])->has('products.all.test'));
    }

    public function test_createOrder_with_single_item_flushes_only_that_product_cache(): void
    {
        $user = UserModel::factory()->create();

        /** @var ProductModel $product */
        $product = ProductModel::factory()->create(['quantity' => 10]);
        /** @var ProductModel $otherProduct */
        $otherProduct = ProductModel::factory()->create(['quantity' => 5]);

        Cache::put("products.{$product->id}", 'cached_value', 300);
        Cache::put("products.{$otherProduct->id}", 'other_cached_value', 300);

        $item = new UnpersistedOrderItem(
            productId: $product->id,
            quantity: 3,
            unitPrice: 50.0,
        );

        $unpersisted = new UnpersistedOrder(
            userId: $user->id,
            status: OrderStatus::Pending,
            totalPrice: 150,
            items: [$item],
        );

        $persisted = Order::fromModel(OrderModel::factory()->create());

        $this->productRepository
            ->method('findByIdForUpdate')
            ->willReturn($product);

        $this->inventoryHistoryRepository
            ->method('record')
            ->willReturn(InventoryHistoryEntry::fromModel(InventoryHistoryModel::factory()->create()));

        $this->repository->method('persist')->willReturn($persisted);

        $this->service->createOrder($unpersisted);

        $this->assertFalse(Cache::has("products.{$product->id}"));
        $this->assertTrue(Cache::has("products.{$otherProduct->id}"));
    }
}

