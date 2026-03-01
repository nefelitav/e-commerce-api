<?php

namespace Tests\Unit\CQRS;

use App\CQRS\CommandBus;
use App\CQRS\Commands\CommandInterface;
use App\CQRS\Commands\Order\CreateOrderCommand;
use App\CQRS\Commands\Product\CreateProductCommand;
use App\CQRS\Handlers\Order\CreateOrderCommandHandler;
use App\CQRS\Handlers\Product\CreateProductCommandHandler;
use App\Dto\Order\Order;
use App\Dto\Product\Product;
use App\Enums\OrderStatus;
use App\Models\Order\OrderModel;
use App\Models\Product\ProductModel;
use App\Models\UserModel;
use Illuminate\Contracts\Container\Container;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\TestCase;

class CommandBusTest extends TestCase
{
    use RefreshDatabase;

    /** @var Container&MockObject */
    private Container $container;

    protected function setUp(): void
    {
        parent::setUp();
        $this->container = $this->createMock(Container::class);
    }

    public function test_dispatch_resolves_correct_handler_and_returns_result(): void
    {
        $product = Product::fromModel(ProductModel::factory()->create());

        $command = new CreateProductCommand(
            name:        'Widget',
            description: null,
            price:       5.00,
            quantity:    10,
            categoryId:  1,
        );

        /** @var CreateProductCommandHandler&MockObject $handler */
        $handler = $this->createMock(CreateProductCommandHandler::class);
        $handler->expects($this->once())
            ->method('handle')
            ->with($command)
            ->willReturn($product);

        $this->container
            ->expects($this->once())
            ->method('make')
            ->with(CreateProductCommandHandler::class)
            ->willReturn($handler);

        $bus = new CommandBus(
            container: $this->container,
            handlers:  [CreateProductCommand::class => CreateProductCommandHandler::class],
        );

        $result = $bus->dispatch($command);

        $this->assertSame($product, $result);
    }

    public function test_dispatch_throws_logic_exception_for_unregistered_command(): void
    {
        $bus = new CommandBus(
            container: $this->container,
            handlers:  [],
        );

        $unregisteredCommand = new class implements CommandInterface {};

        $this->expectException(LogicException::class);

        $bus->dispatch($unregisteredCommand);
    }

    public function test_dispatch_routes_different_commands_to_different_handlers(): void
    {
        $user         = UserModel::factory()->create();
        $productModel = ProductModel::factory()->create();
        $orderModel   = OrderModel::factory()->create(['user_id' => $user->id]);

        $product = Product::fromModel($productModel);
        $order   = Order::fromModel($orderModel);

        $productCommand = new CreateProductCommand(
            name: 'X', description: null, price: 1.00, quantity: 1, categoryId: 1,
        );
        $orderCommand = new CreateOrderCommand(
            userId: $user->id, status: OrderStatus::Pending, totalPrice: 1.00, items: [],
        );

        /** @var CreateProductCommandHandler&MockObject $productHandler */
        $productHandler = $this->createMock(CreateProductCommandHandler::class);
        $productHandler->method('handle')->willReturn($product);

        /** @var CreateOrderCommandHandler&MockObject $orderHandler */
        $orderHandler = $this->createMock(CreateOrderCommandHandler::class);
        $orderHandler->method('handle')->willReturn($order);

        $this->container
            ->method('make')
            ->willReturnMap([
                [CreateProductCommandHandler::class, [], $productHandler],
                [CreateOrderCommandHandler::class,   [], $orderHandler],
            ]);

        $bus = new CommandBus(
            container: $this->container,
            handlers:  [
                CreateProductCommand::class => CreateProductCommandHandler::class,
                CreateOrderCommand::class   => CreateOrderCommandHandler::class,
            ],
        );

        $this->assertSame($product, $bus->dispatch($productCommand));
        $this->assertSame($order,   $bus->dispatch($orderCommand));
    }
}

