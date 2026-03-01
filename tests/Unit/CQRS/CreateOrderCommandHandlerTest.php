<?php

namespace Tests\Unit\CQRS;

use App\CQRS\Commands\Order\CreateOrderCommand;
use App\CQRS\Commands\Order\CreateOrderCommandItem;
use App\CQRS\Handlers\Order\CreateOrderCommandHandler;
use App\Dto\Order\Order;
use App\Dto\Order\UnpersistedOrder;
use App\Dto\Order\UnpersistedOrderItem;
use App\Enums\OrderStatus;
use App\Exceptions\InsufficientStockException;
use App\Exceptions\ProductNotFoundException;
use App\Models\Order\OrderModel;
use App\Models\UserModel;
use App\Services\Order\OrderServiceInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\TestCase;

class CreateOrderCommandHandlerTest extends TestCase
{
    use RefreshDatabase;

    /** @var OrderServiceInterface&MockObject */
    private OrderServiceInterface $orderService;
    private CreateOrderCommandHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->orderService = $this->createMock(OrderServiceInterface::class);
        $this->handler      = new CreateOrderCommandHandler($this->orderService);
    }

    public function test_handle_delegates_to_order_service_and_returns_order(): void
    {
        $user  = UserModel::factory()->create();
        $order = Order::fromModel(OrderModel::factory()->create(['user_id' => $user->id]));

        $commandItem = new CreateOrderCommandItem(
            productId: 7,
            quantity:  2,
            unitPrice: 49.99,
        );

        $command = new CreateOrderCommand(
            userId:     $user->id,
            status:     OrderStatus::Pending,
            totalPrice: 99.98,
            items:      [$commandItem],
        );

        $expectedUnpersisted = new UnpersistedOrder(
            userId:     $user->id,
            status:     OrderStatus::Pending,
            totalPrice: 99.98,
            items:      [
                new UnpersistedOrderItem(
                    productId: 7,
                    quantity:  2,
                    unitPrice: 49.99,
                ),
            ],
        );

        $this->orderService
            ->expects($this->once())
            ->method('createOrder')
            ->with($expectedUnpersisted)
            ->willReturn($order);

        $result = $this->handler->handle($command);

        $this->assertSame($order, $result);
    }

    public function test_handle_propagates_product_not_found_exception(): void
    {
        $command = new CreateOrderCommand(
            userId:     1,
            status:     OrderStatus::Pending,
            totalPrice: 10.00,
            items:      [new CreateOrderCommandItem(productId: 999, quantity: 1, unitPrice: 10.00)],
        );

        $this->orderService
            ->expects($this->once())
            ->method('createOrder')
            ->willThrowException(new ProductNotFoundException(999));

        $this->expectException(ProductNotFoundException::class);

        $this->handler->handle($command);
    }

    public function test_handle_propagates_insufficient_stock_exception(): void
    {
        $command = new CreateOrderCommand(
            userId:     1,
            status:     OrderStatus::Pending,
            totalPrice: 100.00,
            items:      [new CreateOrderCommandItem(productId: 1, quantity: 100, unitPrice: 1.00)],
        );

        $this->orderService
            ->expects($this->once())
            ->method('createOrder')
            ->willThrowException(new InsufficientStockException(1, 100, 5));

        $this->expectException(InsufficientStockException::class);

        $this->handler->handle($command);
    }
}


