<?php

namespace Tests\Security;

use App\Enums\OrderStatus;
use App\Models\Order\OrderModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProviderExternal;
use Symfony\Component\HttpFoundation\Response;
use Tests\DataProviders\SecurityDataProvider;
use Tests\Fixtures\OrderFixture;
use Tests\TestCase;
use Tests\Traits\InteractsWithShopApi;

class WebhookSecurityTest extends TestCase
{
    use InteractsWithShopApi;
    use RefreshDatabase;

    public function test_webhook_without_signature_when_secret_configured(): void
    {
        config(['webhooks.signing_secret' => 'my-secret-key']);

        $order = OrderModel::factory()->create(['status' => OrderStatus::Pending->value]);

        $this->postJson(
            route('v1.webhooks.payments'),
            OrderFixture::webhookPayload($order->id, 'pay_no_sig'),
        )->assertStatus(Response::HTTP_FORBIDDEN);

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => OrderStatus::Pending->value]);
    }

    public function test_webhook_with_wrong_signature(): void
    {
        config(['webhooks.signing_secret' => 'correct-secret']);

        $order = OrderModel::factory()->create(['status' => OrderStatus::Pending->value]);

        $this->postJson(
            route('v1.webhooks.payments'),
            OrderFixture::webhookPayload($order->id, 'pay_wrong_sig'),
            ['X-Webhook-Signature' => 'definitely-wrong-signature'],
        )->assertStatus(Response::HTTP_FORBIDDEN);

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => OrderStatus::Pending->value]);
    }

    public function test_webhook_with_correct_signature(): void
    {
        $secret = 'my-webhook-secret';
        config(['webhooks.signing_secret' => $secret]);

        $order = OrderModel::factory()->create(['status' => OrderStatus::Pending->value]);

        $this->payOrderViaSignedWebhook($order->id, $secret, 'pay_valid_sig')
            ->assertStatus(Response::HTTP_OK);

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => OrderStatus::Paid->value]);
    }

    public function test_webhook_without_secret_configured_allows_request(): void
    {
        config(['webhooks.signing_secret' => null]);

        $order = OrderModel::factory()->create(['status' => OrderStatus::Pending->value]);

        $this->payOrderViaWebhook($order->id, 'pay_no_secret')
            ->assertStatus(Response::HTTP_OK);
    }

    public function test_webhook_with_empty_secret_allows_request(): void
    {
        config(['webhooks.signing_secret' => '']);

        $order = OrderModel::factory()->create(['status' => OrderStatus::Pending->value]);

        $this->payOrderViaWebhook($order->id, 'pay_empty_secret')
            ->assertStatus(Response::HTTP_OK);
    }

    public function test_webhook_rejects_tampered_payload_with_valid_old_signature(): void
    {
        $secret = 'tamper-secret';
        config(['webhooks.signing_secret' => $secret]);

        $order = OrderModel::factory()->create(['status' => OrderStatus::Pending->value]);

        // Generate signature for original payload
        $signed = OrderFixture::signedWebhookPayload($order->id, $secret, 'pay_original');

        // Send tampered payload with original signature
        $this->postJson(
            route('v1.webhooks.payments'),
            OrderFixture::webhookPayload($order->id, 'pay_tampered'),
            $signed['headers'],
        )->assertStatus(Response::HTTP_FORBIDDEN);
    }

    public function test_webhook_validates_required_fields(): void
    {
        $this->postJson(route('v1.webhooks.payments'), [])
            ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    #[DataProviderExternal(SecurityDataProvider::class, 'invalidWebhookStatuses')]
    public function test_webhook_rejects_invalid_status_value(string $status): void
    {
        $order = OrderModel::factory()->create(['status' => OrderStatus::Pending->value]);

        $this->postJson(route('v1.webhooks.payments'), [
            'order_id' => $order->id,
            'payment_reference' => 'pay_bad_status',
            'status' => $status,
        ])->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    #[DataProviderExternal(SecurityDataProvider::class, 'sqlInjectionPayloads')]
    public function test_webhook_with_sql_injection_in_payment_reference(string $payload): void
    {
        $order = OrderModel::factory()->create(['status' => OrderStatus::Pending->value]);

        $this->postJson(route('v1.webhooks.payments'), [
            'order_id' => $order->id,
            'payment_reference' => $payload,
            'status' => OrderStatus::Paid->value,
        ])->assertStatus(Response::HTTP_OK);

        $this->assertDatabaseCount('orders', 1);
    }
}
