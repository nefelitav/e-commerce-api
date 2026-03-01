<?php

namespace Tests\Feature\Controllers\Order;

use App\Models\Order\OrderItemModel;
use App\Models\Order\OrderModel;
use App\Models\Product\ProductModel;
use App\Models\UserModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response;
use Tests\TestCase;

class UpdateOrderControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_returns_401(): void
    {
        $order = OrderModel::factory()->create();
        $response = $this->putJson(route('v1.orders.update', $order->id), []);
        $response->assertStatus(Response::HTTP_UNAUTHORIZED);
    }

    public function test_admin_can_update_any_order_with_any_status(): void
    {
        $admin = UserModel::factory()->admin()->create();
        $this->actingAs($admin);

        $order = OrderModel::factory()->create([
            'status' => 'pending',
            'total_price' => 100,
        ]);

        $product = ProductModel::factory()->create();
        OrderItemModel::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 100,
        ]);

        $newProduct = ProductModel::factory()->create();

        $payload = [
            'id' => $order->id,
            'status' => 'paid',
            'total_price' => 200,
            'items' => [
                ['product_id' => $newProduct->id, 'quantity' => 2, 'unit_price' => 100],
            ],
        ];

        $response = $this->putJson(route('v1.orders.update', $order->id), $payload);

        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonFragment(['status' => 'paid']);

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'paid']);
    }

    public function test_regular_user_can_cancel_own_pending_order_within_24_hours(): void
    {
        $user = UserModel::factory()->create();
        $this->actingAs($user);

        $order = OrderModel::factory()->create([
            'user_id' => $user->id,
            'status' => 'pending',
            'total_price' => 100,
        ]);

        $product = ProductModel::factory()->create();
        OrderItemModel::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 100,
        ]);

        $payload = [
            'id' => $order->id,
            'status' => 'cancelled',
            'total_price' => 100,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 100],
            ],
        ];

        $response = $this->putJson(route('v1.orders.update', $order->id), $payload);

        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonFragment(['status' => 'cancelled']);
    }

    public function test_regular_user_cannot_transition_to_non_allowed_status(): void
    {
        $user = UserModel::factory()->create();
        $this->actingAs($user);

        $order = OrderModel::factory()->create([
            'user_id' => $user->id,
            'status' => 'pending',
            'total_price' => 100,
        ]);

        $product = ProductModel::factory()->create();
        OrderItemModel::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 100,
        ]);

        $payload = [
            'id' => $order->id,
            'status' => 'shipped',  // not allowed for users
            'total_price' => 100,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 100],
            ],
        ];

        $response = $this->putJson(route('v1.orders.update', $order->id), $payload);

        $response->assertStatus(Response::HTTP_BAD_REQUEST);
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'pending']);
    }

    public function test_regular_user_cannot_update_other_users_order(): void
    {
        $user = UserModel::factory()->create();
        $other = UserModel::factory()->create();
        $this->actingAs($user);

        $order = OrderModel::factory()->create([
            'user_id' => $other->id,
            'status' => 'pending',
            'total_price' => 100,
        ]);

        $product = ProductModel::factory()->create();
        OrderItemModel::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 100,
        ]);

        $payload = [
            'id' => $order->id,
            'status' => 'cancelled',
            'total_price' => 100,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 100],
            ],
        ];

        $response = $this->putJson(route('v1.orders.update', $order->id), $payload);

        $response->assertStatus(Response::HTTP_BAD_REQUEST);
    }

    public function test_update_order_fails_with_invalid_data(): void
    {
        $admin = UserModel::factory()->admin()->create();
        $this->actingAs($admin);

        $order = OrderModel::factory()->create();

        $response = $this->putJson(route('v1.orders.update', $order->id), [
            'status' => '',
            'total_price' => 100,
        ]);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonValidationErrors(['status']);
    }
}
