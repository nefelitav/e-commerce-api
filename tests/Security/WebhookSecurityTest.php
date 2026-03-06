<?php

namespace Tests\Security;

use App\Models\Order\OrderModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class WebhookSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_webhook_without_signature_when_secret_configured(): void
    {
        config(['webhooks.signing_secret' => 'my-secret-key']);

        $order = OrderModel::factory()->create(['status' => 'pending']);

        $this->postJson(route('v1.webhooks.payments'), [
            'order_id' => $order->id,
            'payment_reference' => 'pay_no_sig',
            'status' => 'paid',
        ])->assertStatus(Response::HTTP_FORBIDDEN);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'pending',
        ]);
    }

    public function test_webhook_with_wrong_signature(): void
    {
        config(['webhooks.signing_secret' => 'correct-secret']);

        $order = OrderModel::factory()->create(['status' => 'pending']);

        $this->postJson(
            route('v1.webhooks.payments'),
            [
                'order_id' => $order->id,
                'payment_reference' => 'pay_wrong_sig',
                'status' => 'paid',
            ],
            ['X-Webhook-Signature' => 'definitely-wrong-signature'],
        )->assertStatus(Response::HTTP_FORBIDDEN);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'pending',
        ]);
    }

    public function test_webhook_with_correct_signature(): void
    {
        $secret = 'my-webhook-secret';
        config(['webhooks.signing_secret' => $secret]);

        $order = OrderModel::factory()->create(['status' => 'pending']);

        $payload = json_encode([
            'order_id' => $order->id,
            'payment_reference' => 'pay_valid_sig',
            'status' => 'paid',
        ], JSON_THROW_ON_ERROR);

        $signature = hash_hmac('sha256', $payload, $secret);

        $this->postJson(
            route('v1.webhooks.payments'),
            json_decode($payload, true, 512, JSON_THROW_ON_ERROR),
            ['X-Webhook-Signature' => $signature],
        )->assertStatus(Response::HTTP_OK);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'paid',
        ]);
    }

    public function test_webhook_without_secret_configured_allows_request(): void
    {
        config(['webhooks.signing_secret' => null]);

        $order = OrderModel::factory()->create(['status' => 'pending']);

        $this->postJson(route('v1.webhooks.payments'), [
            'order_id' => $order->id,
            'payment_reference' => 'pay_no_secret',
            'status' => 'paid',
        ])->assertStatus(Response::HTTP_OK);
    }

    public function test_webhook_with_empty_secret_allows_request(): void
    {
        config(['webhooks.signing_secret' => '']);

        $order = OrderModel::factory()->create(['status' => 'pending']);

        $this->postJson(route('v1.webhooks.payments'), [
            'order_id' => $order->id,
            'payment_reference' => 'pay_empty_secret',
            'status' => 'paid',
        ])->assertStatus(Response::HTTP_OK);
    }

    public function test_webhook_rejects_tampered_payload_with_valid_old_signature(): void
    {
        $secret = 'tamper-secret';
        config(['webhooks.signing_secret' => $secret]);

        $order = OrderModel::factory()->create(['status' => 'pending']);

        $originalPayload = json_encode([
            'order_id' => $order->id,
            'payment_reference' => 'pay_original',
            'status' => 'paid',
        ], JSON_THROW_ON_ERROR);

        $signatureForOriginal = hash_hmac('sha256', $originalPayload, $secret);

        // Send tampered payload with original signature
        $this->postJson(
            route('v1.webhooks.payments'),
            [
                'order_id' => $order->id,
                'payment_reference' => 'pay_tampered',
                'status' => 'paid',
            ],
            ['X-Webhook-Signature' => $signatureForOriginal],
        )->assertStatus(Response::HTTP_FORBIDDEN);
    }

    public function test_webhook_validates_required_fields(): void
    {
        $this->postJson(route('v1.webhooks.payments'), [])
            ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function test_webhook_rejects_invalid_status_value(): void
    {
        $order = OrderModel::factory()->create(['status' => 'pending']);

        $this->postJson(route('v1.webhooks.payments'), [
            'order_id' => $order->id,
            'payment_reference' => 'pay_bad_status',
            'status' => 'shipped',
        ])->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function test_webhook_with_sql_injection_in_payment_reference(): void
    {
        $order = OrderModel::factory()->create(['status' => 'pending']);

        $this->postJson(route('v1.webhooks.payments'), [
            'order_id' => $order->id,
            'payment_reference' => "'; DROP TABLE orders; --",
            'status' => 'paid',
        ])->assertStatus(Response::HTTP_OK);

        $this->assertDatabaseCount('orders', 1);
    }
}
