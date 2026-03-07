<?php

namespace Tests\E2E;

use App\Enums\OrderStatus;
use App\Events\OrderCreatedEvent;
use App\Events\OrderPaidEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Symfony\Component\HttpFoundation\Response;
use Tests\Fixtures\CatalogFixture;
use Tests\Fixtures\OrderFixture;
use Tests\Fixtures\UserFixture;
use Tests\TestCase;
use Tests\Traits\InteractsWithShopApi;

class PaymentWebhookFlowTest extends TestCase
{
    use InteractsWithShopApi;
    use RefreshDatabase;

    public function test_order_creation_payment_and_webhook_signature_verification(): void
    {
        Event::fake([OrderCreatedEvent::class, OrderPaidEvent::class]);

        $customer = UserFixture::customer();
        ['product' => $product] = CatalogFixture::simpleProductInCategory(100.00, 10);

        $this->actingAs($customer);

        $orderResponse = $this->postJson(
            route('v1.orders.store'),
            OrderFixture::payload($product, 2),
        );
        $orderResponse->assertStatus(Response::HTTP_CREATED);
        $orderId = $orderResponse->json('data.id');

        Event::assertDispatched(OrderCreatedEvent::class);
        $this->assertDatabaseHas('orders', ['id' => $orderId, 'status' => OrderStatus::Pending->value]);

        $this->payOrderViaWebhook($orderId, 'pay_webhook_test_001')
            ->assertStatus(Response::HTTP_OK)
            ->assertJsonFragment(['status' => OrderStatus::Paid->value]);

        Event::assertDispatched(OrderPaidEvent::class);
        $this->assertDatabaseHas('orders', ['id' => $orderId, 'status' => OrderStatus::Paid->value]);

        $this->actingAs($customer);
        $this->getJson(route('v1.orders.show', $orderId))
            ->assertStatus(Response::HTTP_OK)
            ->assertJsonFragment(['status' => OrderStatus::Paid->value]);
    }

    /**
     * @throws \JsonException
     */
    public function test_webhook_with_signing_secret_validation(): void
    {
        $secret = 'test-secret-key';
        config(['webhooks.signing_secret' => $secret]);

        ['customer' => $customer, 'product' => $product, 'orderId' => $orderId] = $this->placeOrder();

        $this->payOrderViaSignedWebhook($orderId, $secret, 'pay_signed_001')
            ->assertStatus(Response::HTTP_OK);

        $this->assertDatabaseHas('orders', ['id' => $orderId, 'status' => OrderStatus::Paid->value]);
    }

    public function test_webhook_rejects_invalid_signature(): void
    {
        config(['webhooks.signing_secret' => 'test-secret-key']);

        ['orderId' => $orderId] = $this->placeOrder();

        $this->postJson(
            route('v1.webhooks.payments'),
            OrderFixture::webhookPayload($orderId, 'pay_invalid_sig'),
            ['X-Webhook-Signature' => 'invalid-signature'],
        )->assertStatus(Response::HTTP_FORBIDDEN);

        $this->assertDatabaseHas('orders', ['id' => $orderId, 'status' => OrderStatus::Pending->value]);
    }

    public function test_double_payment_webhook_is_rejected(): void
    {
        ['orderId' => $orderId] = $this->placeOrder();

        $this->payOrderViaWebhook($orderId, 'pay_double_001')
            ->assertStatus(Response::HTTP_OK);

        $this->payOrderViaWebhook($orderId, 'pay_double_001')
            ->assertStatus(Response::HTTP_BAD_REQUEST);

        $this->assertDatabaseHas('orders', ['id' => $orderId, 'status' => OrderStatus::Paid->value]);
    }
}
