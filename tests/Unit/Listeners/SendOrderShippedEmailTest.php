<?php

namespace Tests\Unit\Listeners;

use App\Dto\Order\Order;
use App\Enums\OrderStatus;
use App\Events\OrderShippedEvent;
use App\Listeners\SendOrderShippedEmail;
use App\Mail\OrderShippedMail;
use App\Models\UserModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SendOrderShippedEmailTest extends TestCase
{
    use RefreshDatabase;

    public function test_sends_shipped_email_to_customer(): void
    {
        Mail::fake();

        $user = UserModel::factory()->create([
            'name' => 'Alice Smith',
            'email' => 'alice@example.com',
        ]);

        $order = new Order(
            id: 7,
            userId: $user->id,
            status: OrderStatus::Shipped,
            totalPrice: 149.50,
            createdAt: now()->toIso8601String(),
        );

        $listener = new SendOrderShippedEmail();
        $listener->handle(new OrderShippedEvent($order));

        Mail::assertQueued(OrderShippedMail::class, function (OrderShippedMail $mail) use ($user) {
            return $mail->hasTo($user->email)
                && $mail->customerName === 'Alice Smith'
                && $mail->order->id === 7;
        });
    }

    public function test_skips_when_user_not_found(): void
    {
        Mail::fake();

        $order = new Order(
            id: 1,
            userId: 99999,
            status: OrderStatus::Shipped,
            totalPrice: 100,
            createdAt: now()->toIso8601String(),
        );

        $listener = new SendOrderShippedEmail();
        $listener->handle(new OrderShippedEvent($order));

        Mail::assertNothingQueued();
    }

    public function test_listener_is_queued(): void
    {
        $listener = new SendOrderShippedEmail();

        $this->assertInstanceOf(\Illuminate\Contracts\Queue\ShouldQueue::class, $listener);
        $this->assertEquals('emails', $listener->queue);
        $this->assertEquals(3, $listener->tries);
    }
}

