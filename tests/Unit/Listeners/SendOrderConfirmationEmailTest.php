<?php

namespace Tests\Unit\Listeners;

use App\Dto\Order\Order;
use App\Dto\Order\OrderItem;
use App\Enums\OrderStatus;
use App\Events\OrderCreatedEvent;
use App\Listeners\SendOrderConfirmationEmail;
use App\Mail\OrderConfirmationMail;
use App\Models\UserModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SendOrderConfirmationEmailTest extends TestCase
{
    use RefreshDatabase;

    public function test_sends_confirmation_email_to_customer(): void
    {
        Mail::fake();

        $user = UserModel::factory()->create([
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
        ]);

        $order = new Order(
            id: 1,
            userId: $user->id,
            status: OrderStatus::Pending,
            totalPrice: 199.99,
            createdAt: now()->toIso8601String(),
            items: [
                new OrderItem(id: 1, orderId: 1, productId: 10, quantity: 2, unitPrice: 99.99),
            ],
        );

        $listener = new SendOrderConfirmationEmail();
        $listener->handle(new OrderCreatedEvent($order));

        Mail::assertQueued(OrderConfirmationMail::class, function (OrderConfirmationMail $mail) use ($user) {
            return $mail->hasTo($user->email)
                && $mail->customerName === 'Jane Doe'
                && $mail->order->id === 1;
        });
    }

    public function test_skips_when_user_not_found(): void
    {
        Mail::fake();

        $order = new Order(
            id: 1,
            userId: 99999,
            status: OrderStatus::Pending,
            totalPrice: 100,
            createdAt: now()->toIso8601String(),
        );

        $listener = new SendOrderConfirmationEmail();
        $listener->handle(new OrderCreatedEvent($order));

        Mail::assertNothingQueued();
    }

    public function test_listener_is_queued(): void
    {
        $listener = new SendOrderConfirmationEmail();

        $this->assertInstanceOf(\Illuminate\Contracts\Queue\ShouldQueue::class, $listener);
        $this->assertEquals('emails', $listener->queue);
        $this->assertEquals(3, $listener->tries);
    }
}

