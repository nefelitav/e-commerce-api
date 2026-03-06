<?php

namespace Tests\Security;

use App\Models\Order\OrderModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProviderExternal;
use Symfony\Component\HttpFoundation\Response;
use Tests\DataProviders\SecurityDataProvider;
use Tests\Fixtures\CatalogFixture;
use Tests\Fixtures\OrderFixture;
use Tests\Fixtures\UserFixture;
use Tests\TestCase;
use Tests\Traits\InteractsWithShopApi;

class OrderStateSecurityTest extends TestCase
{
    use InteractsWithShopApi;
    use RefreshDatabase;

    public function test_user_cannot_create_order_with_paid_status(): void
    {
        $user = UserFixture::customer();
        $this->actingAs($user);

        $product = CatalogFixture::productWithStock(10);

        $response = $this->postJson(route('v1.orders.store'), [
            'status' => 'paid',
            'total_price' => $product->price,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1, 'unit_price' => $product->price],
            ],
        ]);

        $this->assertContains(
            $response->getStatusCode(),
            [Response::HTTP_CREATED, Response::HTTP_BAD_REQUEST, Response::HTTP_UNPROCESSABLE_ENTITY],
        );
    }

    public function test_user_cannot_create_order_with_shipped_status(): void
    {
        $user = UserFixture::customer();
        $this->actingAs($user);

        $product = CatalogFixture::productWithStock(10);

        $response = $this->postJson(
            route('v1.orders.store'),
            OrderFixture::payload($product, 1, 'shipped'),
        );

        $this->assertContains(
            $response->getStatusCode(),
            [Response::HTTP_CREATED, Response::HTTP_BAD_REQUEST, Response::HTTP_UNPROCESSABLE_ENTITY],
        );
    }

    public function test_user_cannot_create_order_with_delivered_status(): void
    {
        $user = UserFixture::customer();
        $this->actingAs($user);

        $product = CatalogFixture::productWithStock(10);

        $response = $this->postJson(
            route('v1.orders.store'),
            OrderFixture::payload($product, 1, 'delivered'),
        );

        $this->assertContains(
            $response->getStatusCode(),
            [Response::HTTP_CREATED, Response::HTTP_BAD_REQUEST, Response::HTTP_UNPROCESSABLE_ENTITY],
        );
    }

    // ---------------------------------------------------------------
    // Invalid transitions from pending (data-provider driven)
    // ---------------------------------------------------------------

    #[DataProviderExternal(SecurityDataProvider::class, 'invalidTransitionsFromPending')]
    public function test_user_cannot_transition_pending_to_invalid_status(string $targetStatus): void
    {
        ['customer' => $customer, 'product' => $product, 'orderId' => $orderId] = $this->placeOrder();

        $this->actingAs($customer);

        $this->updateOrderStatus($orderId, $targetStatus, $product)
            ->assertStatus(Response::HTTP_BAD_REQUEST);

        $this->assertDatabaseHas('orders', ['id' => $orderId, 'status' => 'pending']);
    }

    public function test_user_cannot_transition_cancelled_to_any_status(): void
    {
        ['customer' => $customer, 'product' => $product, 'orderId' => $orderId] = $this->placeOrder();

        $this->actingAs($customer);

        // First cancel the order
        $this->updateOrderStatus($orderId, 'cancelled', $product)
            ->assertStatus(Response::HTTP_OK);

        // Try to revert cancelled to pending
        $this->updateOrderStatus($orderId, 'pending', $product)
            ->assertStatus(Response::HTTP_BAD_REQUEST);
    }

    public function test_order_cannot_be_oversold_beyond_stock(): void
    {
        $user = UserFixture::customer();
        $this->actingAs($user);

        $product = CatalogFixture::productWithStock(3, 10);

        $this->postJson(route('v1.orders.store'), OrderFixture::payload($product, 5))
            ->assertStatus(Response::HTTP_BAD_REQUEST);

        $this->assertDatabaseHas('products', ['id' => $product->id, 'quantity' => 3]);
    }

    // ---------------------------------------------------------------
    // Webhook cannot bypass state machine (data-provider driven)
    // ---------------------------------------------------------------

    #[DataProviderExternal(SecurityDataProvider::class, 'nonPayableOrderStatuses')]
    public function test_payment_webhook_cannot_pay_order_in_status(string $status): void
    {
        $order = OrderModel::factory()->create(['status' => $status]);

        $this->postJson(
            route('v1.webhooks.payments'),
            OrderFixture::webhookPayload($order->id, "pay_{$status}"),
        )->assertStatus(Response::HTTP_BAD_REQUEST);

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => $status]);
    }
}
