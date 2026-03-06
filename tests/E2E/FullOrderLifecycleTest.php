<?php

namespace Tests\E2E;

use App\Events\OrderCreatedEvent;
use App\Events\OrderPaidEvent;
use App\Events\OrderShippedEvent;
use App\Models\Category\CategoryModel;
use App\Models\Product\ProductModel;
use App\Models\UserModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

/**
 * Full end-to-end test covering the complete order lifecycle:
 * Browse products → Create order → Pay → Ship → Deliver
 */
class FullOrderLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_complete_order_lifecycle_browse_order_pay_ship_deliver(): void
    {
        Event::fake([OrderCreatedEvent::class, OrderPaidEvent::class, OrderShippedEvent::class]);

        $admin = UserModel::factory()->admin()->create();
        $customer = UserModel::factory()->create();

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
        $productOneResponse = $this->postJson(route('v1.products.store'), [
            'name' => 'Wireless Headphones',
            'description' => 'High-quality noise-cancelling headphones',
            'price' => 199.99,
            'quantity' => 50,
            'category_id' => $categoryId,
        ]);

        $productOneResponse->assertStatus(Response::HTTP_CREATED);
        $productOneId = $productOneResponse->json('data.id');

        $productTwoResponse = $this->postJson(route('v1.products.store'), [
            'name' => 'USB-C Cable',
            'description' => 'Fast charging cable',
            'price' => 14.99,
            'quantity' => 200,
            'category_id' => $categoryId,
        ]);

        $productTwoResponse->assertStatus(Response::HTTP_CREATED);
        $productTwoId = $productTwoResponse->json('data.id');

        // Step 3: Customer browses the catalog (public endpoints)
        $this->actingAs($customer);

        $listResponse = $this->getJson(route('v1.products.index'));
        $listResponse->assertStatus(Response::HTTP_OK)
            ->assertJsonFragment(['name' => 'Wireless Headphones'])
            ->assertJsonFragment(['name' => 'USB-C Cable']);

        // Step 4: Customer views individual product details
        $productDetailResponse = $this->getJson(route('v1.products.show', $productOneId));
        $productDetailResponse->assertStatus(Response::HTTP_OK)
            ->assertJsonFragment(['name' => 'Wireless Headphones']);

        // Step 5: Customer places an order with multiple items
        $totalPrice = (199.99 * 2) + (14.99 * 3);

        $orderResponse = $this->postJson(route('v1.orders.store'), [
            'status' => 'pending',
            'total_price' => $totalPrice,
            'items' => [
                [
                    'product_id' => $productOneId,
                    'quantity' => 2,
                    'unit_price' => 199.99,
                ],
                [
                    'product_id' => $productTwoId,
                    'quantity' => 3,
                    'unit_price' => 14.99,
                ],
            ],
        ]);

        $orderResponse->assertStatus(Response::HTTP_CREATED);
        $orderId = $orderResponse->json('data.id');

        Event::assertDispatched(OrderCreatedEvent::class);

        // Step 6: Verify stock was decremented
        $this->assertDatabaseHas('products', [
            'id' => $productOneId,
            'quantity' => 48, // 50 - 2
        ]);

        $this->assertDatabaseHas('products', [
            'id' => $productTwoId,
            'quantity' => 197, // 200 - 3
        ]);

        // Step 7: Verify inventory history was recorded
        $this->assertDatabaseHas('inventory_history', [
            'product_id' => $productOneId,
            'change_type' => 'sale',
            'quantity_changed' => -2,
        ]);

        $this->assertDatabaseHas('inventory_history', [
            'product_id' => $productTwoId,
            'change_type' => 'sale',
            'quantity_changed' => -3,
        ]);

        // Step 8: Customer views order
        $orderDetailResponse = $this->getJson(route('v1.orders.show', $orderId));
        $orderDetailResponse->assertStatus(Response::HTTP_OK)
            ->assertJsonFragment(['status' => 'pending']);

        // Step 9: Payment webhook marks order as paid
        $this->postJson(route('v1.webhooks.payments'), [
            'order_id' => $orderId,
            'payment_reference' => 'pay_test_123',
            'status' => 'paid',
        ])->assertStatus(Response::HTTP_OK);

        Event::assertDispatched(OrderPaidEvent::class);

        $this->assertDatabaseHas('orders', [
            'id' => $orderId,
            'status' => 'paid',
        ]);

        // Step 10: Admin ships the order
        $this->actingAs($admin);

        $product1 = ProductModel::find($productOneId);
        $product2 = ProductModel::find($productTwoId);

        $shipResponse = $this->putJson(route('v1.orders.update', $orderId), [
            'status' => 'shipped',
            'total_price' => $totalPrice,
            'items' => [
                [
                    'product_id' => $productOneId,
                    'quantity' => 2,
                    'unit_price' => 199.99,
                ],
                [
                    'product_id' => $productTwoId,
                    'quantity' => 3,
                    'unit_price' => 14.99,
                ],
            ],
        ]);

        $shipResponse->assertStatus(Response::HTTP_OK)
            ->assertJsonFragment(['status' => 'shipped']);

        Event::assertDispatched(OrderShippedEvent::class);

        // Step 11: Admin marks order as delivered
        $deliverResponse = $this->putJson(route('v1.orders.update', $orderId), [
            'status' => 'delivered',
            'total_price' => $totalPrice,
            'items' => [
                [
                    'product_id' => $productOneId,
                    'quantity' => 2,
                    'unit_price' => 199.99,
                ],
                [
                    'product_id' => $productTwoId,
                    'quantity' => 3,
                    'unit_price' => 14.99,
                ],
            ],
        ]);

        $deliverResponse->assertStatus(Response::HTTP_OK)
            ->assertJsonFragment(['status' => 'delivered']);

        $this->assertDatabaseHas('orders', [
            'id' => $orderId,
            'status' => 'delivered',
        ]);
    }

    public function test_customer_cancels_pending_order_and_stock_is_restored_on_delete(): void
    {
        $admin = UserModel::factory()->admin()->create();
        $customer = UserModel::factory()->create();

        $category = CategoryModel::factory()->create();
        $product = ProductModel::factory()->create([
            'name' => 'Smartphone',
            'price' => 999.99,
            'quantity' => 10,
            'category_id' => $category->id,
        ]);

        // Customer places an order
        $this->actingAs($customer);

        $orderResponse = $this->postJson(route('v1.orders.store'), [
            'status' => 'pending',
            'total_price' => 999.99,
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 1,
                    'unit_price' => 999.99,
                ],
            ],
        ]);

        $orderResponse->assertStatus(Response::HTTP_CREATED);
        $orderId = $orderResponse->json('data.id');

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'quantity' => 9,
        ]);

        // Customer cancels the order
        $cancelResponse = $this->putJson(route('v1.orders.update', $orderId), [
            'status' => 'cancelled',
            'total_price' => 999.99,
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 1,
                    'unit_price' => 999.99,
                ],
            ],
        ]);

        $cancelResponse->assertStatus(Response::HTTP_OK)
            ->assertJsonFragment(['status' => 'cancelled']);

        // Admin deletes the cancelled order; stock is restored
        $this->actingAs($admin);

        $deleteResponse = $this->deleteJson(route('v1.orders.destroy', $orderId));
        $deleteResponse->assertStatus(Response::HTTP_NO_CONTENT);

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'quantity' => 10,
        ]);

        $this->assertDatabaseHas('inventory_history', [
            'product_id' => $product->id,
            'change_type' => 'return',
        ]);
    }

    public function test_multiple_customers_ordering_same_product_tracks_stock_correctly(): void
    {
        $customerOne = UserModel::factory()->create();
        $customerTwo = UserModel::factory()->create();

        $category = CategoryModel::factory()->create();
        $product = ProductModel::factory()->create([
            'price' => 49.99,
            'quantity' => 5,
            'category_id' => $category->id,
        ]);

        // Customer 1 orders 3 units
        $this->actingAs($customerOne);

        $this->postJson(route('v1.orders.store'), [
            'status' => 'pending',
            'total_price' => 149.97,
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 3,
                    'unit_price' => 49.99,
                ],
            ],
        ])->assertStatus(Response::HTTP_CREATED);

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'quantity' => 2,
        ]);

        // Customer 2 orders 2 units (remaining stock)
        $this->actingAs($customerTwo);

        $this->postJson(route('v1.orders.store'), [
            'status' => 'pending',
            'total_price' => 99.98,
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 2,
                    'unit_price' => 49.99,
                ],
            ],
        ])->assertStatus(Response::HTTP_CREATED);

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'quantity' => 0,
        ]);

        // Customer 2 tries to order 1 more but stock is depleted
        $this->postJson(route('v1.orders.store'), [
            'status' => 'pending',
            'total_price' => 49.99,
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 1,
                    'unit_price' => 49.99,
                ],
            ],
        ])->assertStatus(Response::HTTP_BAD_REQUEST);
    }
}
