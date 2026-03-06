<?php

namespace Tests\E2E;

use App\Events\OrderCreatedEvent;
use App\Events\OrderPaidEvent;
use App\Models\Category\CategoryModel;
use App\Models\Product\ProductModel;
use App\Models\UserModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class PaymentWebhookFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_creation_payment_and_webhook_signature_verification(): void
    {
        Event::fake([OrderCreatedEvent::class, OrderPaidEvent::class]);

        $customer = UserModel::factory()->create();
        $category = CategoryModel::factory()->create();
        $product = ProductModel::factory()->create([
            'price' => 100.00,
            'quantity' => 10,
            'category_id' => $category->id,
        ]);

        $this->actingAs($customer);

        $orderResponse = $this->postJson(route('v1.orders.store'), [
            'status' => 'pending',
            'total_price' => 200.00,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 2, 'unit_price' => 100.00],
            ],
        ]);
        $orderResponse->assertStatus(Response::HTTP_CREATED);
        $orderId = $orderResponse->json('data.id');

        Event::assertDispatched(OrderCreatedEvent::class);

        $this->assertDatabaseHas('orders', [
            'id' => $orderId,
            'status' => 'pending',
        ]);

        $webhookPayload = [
            'order_id' => $orderId,
            'payment_reference' => 'pay_webhook_test_001',
            'status' => 'paid',
        ];

        $this->postJson(route('v1.webhooks.payments'), $webhookPayload)
            ->assertStatus(Response::HTTP_OK)
            ->assertJsonFragment(['status' => 'paid']);

        Event::assertDispatched(OrderPaidEvent::class);

        $this->assertDatabaseHas('orders', [
            'id' => $orderId,
            'status' => 'paid',
        ]);

        $this->actingAs($customer);
        $this->getJson(route('v1.orders.show', $orderId))
            ->assertStatus(Response::HTTP_OK)
            ->assertJsonFragment(['status' => 'paid']);
    }

    /**
     * @throws \JsonException
     */
    public function test_webhook_with_signing_secret_validation(): void
    {
        config(['webhooks.signing_secret' => 'test-secret-key']);

        $customer = UserModel::factory()->create();
        $product = ProductModel::factory()->create(['quantity' => 10]);

        $this->actingAs($customer);
        $orderResponse = $this->postJson(route('v1.orders.store'), [
            'status' => 'pending',
            'total_price' => $product->price,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1, 'unit_price' => $product->price],
            ],
        ]);
        $orderId = $orderResponse->json('data.id');

        $payload = json_encode([
            'order_id' => $orderId,
            'payment_reference' => 'pay_signed_001',
            'status' => 'paid',
        ], JSON_THROW_ON_ERROR);

        self::assertNotFalse($payload);

        $signature = hash_hmac('sha256', $payload, 'test-secret-key');

        $this->postJson(
            route('v1.webhooks.payments'),
            json_decode($payload, true, 512, JSON_THROW_ON_ERROR),
            ['X-Webhook-Signature' => $signature],
        )->assertStatus(Response::HTTP_OK);

        $this->assertDatabaseHas('orders', [
            'id' => $orderId,
            'status' => 'paid',
        ]);
    }

    public function test_webhook_rejects_invalid_signature(): void
    {
        config(['webhooks.signing_secret' => 'test-secret-key']);

        $customer = UserModel::factory()->create();
        $product = ProductModel::factory()->create(['quantity' => 10]);

        $this->actingAs($customer);
        $orderResponse = $this->postJson(route('v1.orders.store'), [
            'status' => 'pending',
            'total_price' => $product->price,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1, 'unit_price' => $product->price],
            ],
        ]);
        $orderId = $orderResponse->json('data.id');

        $this->postJson(
            route('v1.webhooks.payments'),
            [
                'order_id' => $orderId,
                'payment_reference' => 'pay_invalid_sig',
                'status' => 'paid',
            ],
            ['X-Webhook-Signature' => 'invalid-signature'],
        )->assertStatus(Response::HTTP_FORBIDDEN);

        $this->assertDatabaseHas('orders', [
            'id' => $orderId,
            'status' => 'pending',
        ]);
    }

    public function test_double_payment_webhook_is_rejected(): void
    {
        $customer = UserModel::factory()->create();
        $product = ProductModel::factory()->create(['quantity' => 10]);

        $this->actingAs($customer);
        $orderResponse = $this->postJson(route('v1.orders.store'), [
            'status' => 'pending',
            'total_price' => $product->price,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1, 'unit_price' => $product->price],
            ],
        ]);
        $orderId = $orderResponse->json('data.id');

        $webhookPayload = [
            'order_id' => $orderId,
            'payment_reference' => 'pay_double_001',
            'status' => 'paid',
        ];

        $this->postJson(route('v1.webhooks.payments'), $webhookPayload)
            ->assertStatus(Response::HTTP_OK);

        $this->postJson(route('v1.webhooks.payments'), $webhookPayload)
            ->assertStatus(Response::HTTP_BAD_REQUEST);

        $this->assertDatabaseHas('orders', [
            'id' => $orderId,
            'status' => 'paid',
        ]);
    }
}
