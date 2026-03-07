<?php

namespace Tests\Feature\Controllers\Webhook;

use App\Enums\OrderStatus;
use App\Events\OrderPaidEvent;
use App\Models\Order\OrderModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class PaymentWebhookControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_payment_webhook_marks_pending_order_as_paid(): void
    {
        Event::fake([OrderPaidEvent::class]);

        $order = OrderModel::factory()->create(['status' => OrderStatus::Pending->value]);

        $response = $this->postJson(route('v1.webhooks.payments'), [
            'order_id' => $order->id,
            'payment_reference' => 'pay_abc123',
            'status' => OrderStatus::Paid->value,
        ]);

        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonFragment(['status' => OrderStatus::Paid->value]);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => OrderStatus::Paid->value,
        ]);

        Event::assertDispatched(OrderPaidEvent::class);
    }

    public function test_payment_webhook_rejects_non_pending_order(): void
    {
        $order = OrderModel::factory()->create(['status' => OrderStatus::Shipped->value]);

        $response = $this->postJson(route('v1.webhooks.payments'), [
            'order_id' => $order->id,
            'payment_reference' => 'pay_abc123',
            'status' => OrderStatus::Paid->value,
        ]);

        $response->assertStatus(Response::HTTP_BAD_REQUEST);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => OrderStatus::Shipped->value,
        ]);
    }

    public function test_payment_webhook_rejects_nonexistent_order(): void
    {
        $response = $this->postJson(route('v1.webhooks.payments'), [
            'order_id' => 99999,
            'payment_reference' => 'pay_abc123',
            'status' => OrderStatus::Paid->value,
        ]);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function test_payment_webhook_validates_required_fields(): void
    {
        $response = $this->postJson(route('v1.webhooks.payments'), []);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function test_payment_webhook_rejects_invalid_status(): void
    {
        $order = OrderModel::factory()->create(['status' => OrderStatus::Pending->value]);

        $response = $this->postJson(route('v1.webhooks.payments'), [
            'order_id' => $order->id,
            'payment_reference' => 'pay_abc123',
            'status' => OrderStatus::Shipped->value,
        ]);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function test_payment_webhook_is_idempotent_for_already_paid_order(): void
    {
        $order = OrderModel::factory()->create(['status' => OrderStatus::Paid->value]);

        $response = $this->postJson(route('v1.webhooks.payments'), [
            'order_id' => $order->id,
            'payment_reference' => 'pay_abc123',
            'status' => OrderStatus::Paid->value,
        ]);

        $response->assertStatus(Response::HTTP_BAD_REQUEST);
    }

    public function test_payment_webhook_does_not_require_authentication(): void
    {
        Event::fake([OrderPaidEvent::class]);

        $order = OrderModel::factory()->create(['status' => OrderStatus::Pending->value]);

        $response = $this->postJson(route('v1.webhooks.payments'), [
            'order_id' => $order->id,
            'payment_reference' => 'pay_xyz789',
            'status' => OrderStatus::Paid->value,
        ]);

        $response->assertStatus(Response::HTTP_OK);
    }

    public function test_payment_webhook_rejects_missing_signature_when_secret_configured(): void
    {
        config(['webhooks.signing_secret' => 'test-secret-key']);

        $order = OrderModel::factory()->create(['status' => OrderStatus::Pending->value]);

        $response = $this->postJson(route('v1.webhooks.payments'), [
            'order_id' => $order->id,
            'payment_reference' => 'pay_abc123',
            'status' => OrderStatus::Paid->value,
        ]);

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    public function test_payment_webhook_rejects_invalid_signature(): void
    {
        config(['webhooks.signing_secret' => 'test-secret-key']);

        $order = OrderModel::factory()->create(['status' => OrderStatus::Pending->value]);

        $response = $this->postJson(
            route('v1.webhooks.payments'),
            [
                'order_id' => $order->id,
                'payment_reference' => 'pay_abc123',
                'status' => OrderStatus::Paid->value,
            ],
            ['X-Webhook-Signature' => 'invalid-signature'],
        );

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    public function test_payment_webhook_accepts_valid_signature(): void
    {
        Event::fake([OrderPaidEvent::class]);

        $secret = 'test-secret-key';
        config(['webhooks.signing_secret' => $secret]);

        $order = OrderModel::factory()->create(['status' => OrderStatus::Pending->value]);

        $payload = [
            'order_id' => $order->id,
            'payment_reference' => 'pay_signed123',
            'status' => OrderStatus::Paid->value,
        ];

        $signature = hash_hmac('sha256', (string) json_encode($payload), $secret);

        $response = $this->postJson(
            route('v1.webhooks.payments'),
            $payload,
            ['X-Webhook-Signature' => $signature],
        );

        $response->assertStatus(Response::HTTP_OK);
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => OrderStatus::Paid->value,
        ]);
    }
}
