<?php

namespace Tests\Feature\Controllers\Webhook;

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

        $order = OrderModel::factory()->create(['status' => 'pending']);

        $response = $this->postJson(route('v1.webhooks.payments'), [
            'order_id' => $order->id,
            'payment_reference' => 'pay_abc123',
            'status' => 'paid',
        ]);

        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonFragment(['status' => 'paid']);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'paid',
        ]);

        Event::assertDispatched(OrderPaidEvent::class);
    }

    public function test_payment_webhook_rejects_non_pending_order(): void
    {
        $order = OrderModel::factory()->create(['status' => 'shipped']);

        $response = $this->postJson(route('v1.webhooks.payments'), [
            'order_id' => $order->id,
            'payment_reference' => 'pay_abc123',
            'status' => 'paid',
        ]);

        $response->assertStatus(Response::HTTP_BAD_REQUEST);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'shipped',
        ]);
    }

    public function test_payment_webhook_rejects_nonexistent_order(): void
    {
        $response = $this->postJson(route('v1.webhooks.payments'), [
            'order_id' => 99999,
            'payment_reference' => 'pay_abc123',
            'status' => 'paid',
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
        $order = OrderModel::factory()->create(['status' => 'pending']);

        $response = $this->postJson(route('v1.webhooks.payments'), [
            'order_id' => $order->id,
            'payment_reference' => 'pay_abc123',
            'status' => 'shipped',
        ]);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function test_payment_webhook_is_idempotent_for_already_paid_order(): void
    {
        $order = OrderModel::factory()->create(['status' => 'paid']);

        $response = $this->postJson(route('v1.webhooks.payments'), [
            'order_id' => $order->id,
            'payment_reference' => 'pay_abc123',
            'status' => 'paid',
        ]);

        $response->assertStatus(Response::HTTP_BAD_REQUEST);
    }

    public function test_payment_webhook_does_not_require_authentication(): void
    {
        Event::fake([OrderPaidEvent::class]);

        $order = OrderModel::factory()->create(['status' => 'pending']);

        $response = $this->postJson(route('v1.webhooks.payments'), [
            'order_id' => $order->id,
            'payment_reference' => 'pay_xyz789',
            'status' => 'paid',
        ]);

        $response->assertStatus(Response::HTTP_OK);
    }

    public function test_payment_webhook_rejects_missing_signature_when_secret_configured(): void
    {
        config(['webhooks.signing_secret' => 'test-secret-key']);

        $order = OrderModel::factory()->create(['status' => 'pending']);

        $response = $this->postJson(route('v1.webhooks.payments'), [
            'order_id' => $order->id,
            'payment_reference' => 'pay_abc123',
            'status' => 'paid',
        ]);

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    public function test_payment_webhook_rejects_invalid_signature(): void
    {
        config(['webhooks.signing_secret' => 'test-secret-key']);

        $order = OrderModel::factory()->create(['status' => 'pending']);

        $response = $this->postJson(
            route('v1.webhooks.payments'),
            [
                'order_id' => $order->id,
                'payment_reference' => 'pay_abc123',
                'status' => 'paid',
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

        $order = OrderModel::factory()->create(['status' => 'pending']);

        $payload = [
            'order_id' => $order->id,
            'payment_reference' => 'pay_signed123',
            'status' => 'paid',
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
            'status' => 'paid',
        ]);
    }
}

