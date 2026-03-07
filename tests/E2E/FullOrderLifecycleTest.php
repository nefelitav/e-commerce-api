<?php

namespace Tests\E2E;

use App\Enums\InventoryChangeType;
use App\Enums\OrderStatus;
use App\Events\OrderCreatedEvent;
use App\Events\OrderPaidEvent;
use App\Events\OrderShippedEvent;
use App\Models\Product\ProductModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Symfony\Component\HttpFoundation\Response;
use Tests\Fixtures\CatalogFixture;
use Tests\Fixtures\OrderFixture;
use Tests\Fixtures\UserFixture;
use Tests\TestCase;
use Tests\Traits\InteractsWithShopApi;

/**
 * Full end-to-end test covering the complete order lifecycle:
 * Browse products → Create order → Pay → Ship → Deliver
 */
class FullOrderLifecycleTest extends TestCase
{
    use InteractsWithShopApi;
    use RefreshDatabase;

    public function test_complete_order_lifecycle_browse_order_pay_ship_deliver(): void
    {
        Event::fake([OrderCreatedEvent::class, OrderPaidEvent::class, OrderShippedEvent::class]);

        ['admin' => $admin, 'customer' => $customer] = UserFixture::adminAndCustomer();

        // Step 1: Admin creates a category
        $this->actingAs($admin);

        $categoryResponse = $this->postJson(route('v1.categories.store'), [
            'name' => 'Electronics',
            'description' => 'Electronic devices and accessories',
            'parent_id' => null,
        ]);

        $categoryResponse->assertStatus(Response::HTTP_CREATED);
        $categoryId = $categoryResponse->json('data.id');

        // Step 2: Admin creates products in the category
        ['productId' => $productOneId] = $this->createProductViaApi(
            name: 'Wireless Headphones',
            price: 199.99,
            quantity: 50,
            categoryId: $categoryId,
            admin: $admin,
        );

        ['productId' => $productTwoId] = $this->createProductViaApi(
            name: 'USB-C Cable',
            price: 14.99,
            quantity: 200,
            categoryId: $categoryId,
            admin: $admin,
        );

        // Step 3: Customer browses the catalog (public endpoints)
        $this->actingAs($customer);

        $this->getJson(route('v1.products.index'))
            ->assertStatus(Response::HTTP_OK)
            ->assertJsonFragment(['name' => 'Wireless Headphones'])
            ->assertJsonFragment(['name' => 'USB-C Cable']);

        // Step 4: Customer views individual product details
        $this->getJson(route('v1.products.show', $productOneId))
            ->assertStatus(Response::HTTP_OK)
            ->assertJsonFragment(['name' => 'Wireless Headphones']);

        // Step 5: Customer places an order with multiple items
        $totalPrice = (199.99 * 2) + (14.99 * 3);

        $productOne = ProductModel::findOrFail($productOneId);
        $productTwo = ProductModel::findOrFail($productTwoId);

        $orderResponse = $this->postJson(
            route('v1.orders.store'),
            OrderFixture::multiItemPayload([
                ['product' => $productOne, 'quantity' => 2],
                ['product' => $productTwo, 'quantity' => 3],
            ]),
        );

        $orderResponse->assertStatus(Response::HTTP_CREATED);
        $orderId = $orderResponse->json('data.id');

        Event::assertDispatched(OrderCreatedEvent::class);

        // Step 6: Verify stock was decremented
        $this->assertDatabaseHas('products', ['id' => $productOneId, 'quantity' => 48]);
        $this->assertDatabaseHas('products', ['id' => $productTwoId, 'quantity' => 197]);

        // Step 7: Verify inventory history was recorded
        $this->assertDatabaseHas('inventory_history', [
            'product_id' => $productOneId,
            'change_type' => InventoryChangeType::Sale->value,
            'quantity_changed' => -2,
        ]);
        $this->assertDatabaseHas('inventory_history', [
            'product_id' => $productTwoId,
            'change_type' => InventoryChangeType::Sale->value,
            'quantity_changed' => -3,
        ]);

        // Step 8: Customer views order
        $this->getJson(route('v1.orders.show', $orderId))
            ->assertStatus(Response::HTTP_OK)
            ->assertJsonFragment(['status' => OrderStatus::Pending->value]);

        // Step 9: Payment webhook marks order as paid
        $this->payOrderViaWebhook($orderId, 'pay_test_123')
            ->assertStatus(Response::HTTP_OK);

        Event::assertDispatched(OrderPaidEvent::class);
        $this->assertDatabaseHas('orders', ['id' => $orderId, 'status' => OrderStatus::Paid->value]);

        // Step 10: Admin starts fulfilment (paid → processing)
        $this->actingAs($admin);

        $this->putJson(route('v1.orders.update', $orderId), [
            'status' => OrderStatus::Processing->value,
            'total_price' => $totalPrice,
            'items' => [
                ['product_id' => $productOneId, 'quantity' => 2, 'unit_price' => 199.99],
                ['product_id' => $productTwoId, 'quantity' => 3, 'unit_price' => 14.99],
            ],
        ])->assertStatus(Response::HTTP_OK)
            ->assertJsonFragment(['status' => OrderStatus::Processing->value]);

        // Step 11: Admin ships the order (processing → shipped)
        $this->putJson(route('v1.orders.update', $orderId), [
            'status' => OrderStatus::Shipped->value,
            'total_price' => $totalPrice,
            'items' => [
                ['product_id' => $productOneId, 'quantity' => 2, 'unit_price' => 199.99],
                ['product_id' => $productTwoId, 'quantity' => 3, 'unit_price' => 14.99],
            ],
        ])->assertStatus(Response::HTTP_OK)
            ->assertJsonFragment(['status' => OrderStatus::Shipped->value]);

        Event::assertDispatched(OrderShippedEvent::class);

        // Step 12: Admin marks order as delivered (shipped → delivered)
        $this->putJson(route('v1.orders.update', $orderId), [
            'status' => OrderStatus::Delivered->value,
            'total_price' => $totalPrice,
            'items' => [
                ['product_id' => $productOneId, 'quantity' => 2, 'unit_price' => 199.99],
                ['product_id' => $productTwoId, 'quantity' => 3, 'unit_price' => 14.99],
            ],
        ])->assertStatus(Response::HTTP_OK)
            ->assertJsonFragment(['status' => OrderStatus::Delivered->value]);

        $this->assertDatabaseHas('orders', ['id' => $orderId, 'status' => OrderStatus::Delivered->value]);
    }

    public function test_customer_cancels_pending_order_and_stock_is_restored_on_delete(): void
    {
        ['admin' => $admin, 'customer' => $customer] = UserFixture::adminAndCustomer();
        ['product' => $product] = CatalogFixture::simpleProductInCategory(999.99, 10);

        // Customer places an order
        $this->actingAs($customer);

        $orderResponse = $this->postJson(
            route('v1.orders.store'),
            OrderFixture::payload($product, 1),
        );
        $orderResponse->assertStatus(Response::HTTP_CREATED);
        $orderId = $orderResponse->json('data.id');

        $this->assertDatabaseHas('products', ['id' => $product->id, 'quantity' => 9]);

        // Customer cancels the order
        $this->updateOrderStatus($orderId, OrderStatus::Cancelled->value, $product)
            ->assertStatus(Response::HTTP_OK)
            ->assertJsonFragment(['status' => OrderStatus::Cancelled->value]);

        // Admin deletes the cancelled order; stock is restored
        $this->actingAs($admin);
        $this->deleteJson(route('v1.orders.destroy', $orderId))
            ->assertStatus(Response::HTTP_NO_CONTENT);

        $this->assertDatabaseHas('products', ['id' => $product->id, 'quantity' => 10]);
        $this->assertDatabaseHas('inventory_history', [
            'product_id' => $product->id,
            'change_type' => InventoryChangeType::Return->value,
        ]);
    }

    public function test_multiple_customers_ordering_same_product_tracks_stock_correctly(): void
    {
        $customerOne = UserFixture::customer();
        $customerTwo = UserFixture::customer();

        ['product' => $product] = CatalogFixture::simpleProductInCategory(49.99, 5);

        // Customer 1 orders 3 units
        $this->actingAs($customerOne);
        $this->postJson(route('v1.orders.store'), OrderFixture::payload($product, 3))
            ->assertStatus(Response::HTTP_CREATED);

        $this->assertDatabaseHas('products', ['id' => $product->id, 'quantity' => 2]);

        // Customer 2 orders 2 units (remaining stock)
        $this->actingAs($customerTwo);
        $this->postJson(route('v1.orders.store'), OrderFixture::payload($product, 2))
            ->assertStatus(Response::HTTP_CREATED);

        $this->assertDatabaseHas('products', ['id' => $product->id, 'quantity' => 0]);

        // Customer 2 tries to order 1 more but stock is depleted
        $this->postJson(route('v1.orders.store'), OrderFixture::payload($product, 1))
            ->assertStatus(Response::HTTP_BAD_REQUEST);
    }
}
