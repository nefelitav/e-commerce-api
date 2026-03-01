<?php

namespace Tests\Unit\Services;

use App\Dto\Order\Order;
use App\Enums\OrderStatus;
use App\Exceptions\InvalidOrderStateException;
use App\Models\Order\OrderModel;
use App\Services\Order\OrderStatusMachine;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderStatusMachineTest extends TestCase
{
    use RefreshDatabase;

    private OrderStatusMachine $machine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->machine = new OrderStatusMachine();
    }

    // -----------------------------------------------------------------------
    // User transitions
    // -----------------------------------------------------------------------

    public function test_user_pending_to_cancelled_within_window_is_allowed(): void
    {
        Carbon::setTestNow(now());

        $order = Order::fromModel(OrderModel::factory()->create([
            'status'     => OrderStatus::Pending->value,
            'created_at' => now()->subHours(2),
        ]));

        $this->expectNotToPerformAssertions();
        $this->machine->assertUserTransitionAllowed($order, OrderStatus::Cancelled);

        Carbon::setTestNow(null);
    }

    public function test_user_pending_to_cancelled_after_window_throws(): void
    {
        Carbon::setTestNow(now());

        $order = Order::fromModel(OrderModel::factory()->create([
            'status'     => OrderStatus::Pending->value,
            'created_at' => now()->subHours(25),
        ]));

        $this->expectException(InvalidOrderStateException::class);
        $this->expectExceptionMessage('Orders can only be cancelled within 24 hours of creation.');

        $this->machine->assertUserTransitionAllowed($order, OrderStatus::Cancelled);

        Carbon::setTestNow(null);
    }

    public function test_user_pending_to_paid_throws(): void
    {
        $order = Order::fromModel(OrderModel::factory()->create(['status' => OrderStatus::Pending->value]));

        $this->expectException(InvalidOrderStateException::class);
        $this->expectExceptionMessage("Transition from 'pending' to 'paid' is not allowed.");

        $this->machine->assertUserTransitionAllowed($order, OrderStatus::Paid);
    }

    public function test_user_pending_to_shipped_throws(): void
    {
        $order = Order::fromModel(OrderModel::factory()->create(['status' => OrderStatus::Pending->value]));

        $this->expectException(InvalidOrderStateException::class);
        $this->machine->assertUserTransitionAllowed($order, OrderStatus::Shipped);
    }

    public function test_user_paid_to_cancelled_throws(): void
    {
        $order = Order::fromModel(OrderModel::factory()->create(['status' => OrderStatus::Paid->value]));

        $this->expectException(InvalidOrderStateException::class);
        $this->machine->assertUserTransitionAllowed($order, OrderStatus::Cancelled);
    }

    // -----------------------------------------------------------------------
    // Admin transitions
    // -----------------------------------------------------------------------

    public function test_admin_pending_to_paid_is_allowed(): void
    {
        $order = Order::fromModel(OrderModel::factory()->create(['status' => OrderStatus::Pending->value]));

        $this->expectNotToPerformAssertions();
        $this->machine->assertAdminTransitionAllowed($order, OrderStatus::Paid);
    }

    public function test_admin_pending_to_cancelled_is_allowed(): void
    {
        $order = Order::fromModel(OrderModel::factory()->create(['status' => OrderStatus::Pending->value]));

        $this->expectNotToPerformAssertions();
        $this->machine->assertAdminTransitionAllowed($order, OrderStatus::Cancelled);
    }

    public function test_admin_paid_to_shipped_is_allowed(): void
    {
        $order = Order::fromModel(OrderModel::factory()->create(['status' => OrderStatus::Paid->value]));

        $this->expectNotToPerformAssertions();
        $this->machine->assertAdminTransitionAllowed($order, OrderStatus::Shipped);
    }

    public function test_admin_paid_to_refunded_is_allowed(): void
    {
        $order = Order::fromModel(OrderModel::factory()->create(['status' => OrderStatus::Paid->value]));

        $this->expectNotToPerformAssertions();
        $this->machine->assertAdminTransitionAllowed($order, OrderStatus::Refunded);
    }

    public function test_admin_shipped_to_delivered_is_allowed(): void
    {
        $order = Order::fromModel(OrderModel::factory()->create(['status' => OrderStatus::Shipped->value]));

        $this->expectNotToPerformAssertions();
        $this->machine->assertAdminTransitionAllowed($order, OrderStatus::Delivered);
    }

    public function test_admin_delivered_to_refunded_is_allowed(): void
    {
        $order = Order::fromModel(OrderModel::factory()->create(['status' => OrderStatus::Delivered->value]));

        $this->expectNotToPerformAssertions();
        $this->machine->assertAdminTransitionAllowed($order, OrderStatus::Refunded);
    }

    public function test_admin_cancelled_is_terminal(): void
    {
        $order = Order::fromModel(OrderModel::factory()->create(['status' => OrderStatus::Cancelled->value]));

        $this->expectException(InvalidOrderStateException::class);
        $this->machine->assertAdminTransitionAllowed($order, OrderStatus::Pending);
    }

    public function test_admin_refunded_is_terminal(): void
    {
        $order = Order::fromModel(OrderModel::factory()->create(['status' => OrderStatus::Refunded->value]));

        $this->expectException(InvalidOrderStateException::class);
        $this->machine->assertAdminTransitionAllowed($order, OrderStatus::Paid);
    }

    public function test_admin_paid_to_delivered_throws(): void
    {
        $order = Order::fromModel(OrderModel::factory()->create(['status' => OrderStatus::Paid->value]));

        $this->expectException(InvalidOrderStateException::class);
        $this->expectExceptionMessage("Transition from 'paid' to 'delivered' is not allowed.");

        $this->machine->assertAdminTransitionAllowed($order, OrderStatus::Delivered);
    }
}
