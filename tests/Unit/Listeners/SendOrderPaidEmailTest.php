<?php

namespace Tests\Unit\Listeners;

use App\Dto\Order\Order;
use App\Enums\OrderStatus;
use App\Events\OrderPaidEvent;
use App\Listeners\SendOrderPaidEmail;
use App\Mail\OrderPaidMail;
use App\Models\UserModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SendOrderPaidEmailTest extends TestCase
{
    use RefreshDatabase;

    public function test_sends_paid_email_to_customer(): void
    {
        Mail::fake();

        $user = UserModel::factory()->create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);

        $order = new Order(
            id: 42,
            userId: $user->id,
            status: OrderStatus::Paid,
            totalPrice: 299.99,
            createdAt: now()->toIso8601String(),
        );

        $listener = new SendOrderPaidEmail();
        $listener->handle(new OrderPaidEvent($order, '2026-03-04T10:30:00+00:00'));

        Mail::assertQueued(OrderPaidMail::class, function (OrderPaidMail $mail) use ($user) {
            return $mail->hasTo($user->email)
                && $mail->customerName === 'John Doe'
                && $mail->order->id === 42;
        });
    }

    public function test_skips_when_user_not_found(): void
    {
        Mail::fake();

        $order = new Order(
            id: 1,
            userId: 99999,
            status: OrderStatus::Paid,
            totalPrice: 100,
            createdAt: now()->toIso8601String(),
        );

        $listener = new SendOrderPaidEmail();
        $listener->handle(new OrderPaidEvent($order, now()->toIso8601String()));

        Mail::assertNothingQueued();
    }

    public function test_listener_is_queued(): void
    {
        $listener = new SendOrderPaidEmail();

        $this->assertInstanceOf(\Illuminate\Contracts\Queue\ShouldQueue::class, $listener);
        $this->assertEquals('emails', $listener->queue);
        $this->assertEquals(3, $listener->tries);
    }
}

