<?php

namespace Tests\Feature\Controllers\Order;

use App\Enums\OrderStatus;
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
            'status' => OrderStatus::Paid->value,
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
            'status' => OrderStatus::Processing->value,
            'total_price' => 200,
            'items' => [
                ['product_id' => $newProduct->id, 'quantity' => 2, 'unit_price' => 100],
            ],
        ];

        $response = $this->putJson(route('v1.orders.update', $order->id), $payload);

        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonFragment(['status' => OrderStatus::Processing->value]);

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => OrderStatus::Processing->value]);
    }

    public function test_regular_user_can_cancel_own_pending_order_within_24_hours(): void
    {
        $user = UserModel::factory()->create();
        $this->actingAs($user);

        $order = OrderModel::factory()->create([
            'user_id' => $user->id,
            'status' => OrderStatus::Pending->value,
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
            'status' => OrderStatus::Cancelled->value,
            'total_price' => 100,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 100],
            ],
        ];

        $response = $this->putJson(route('v1.orders.update', $order->id), $payload);

        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonFragment(['status' => OrderStatus::Cancelled->value]);
    }

    public function test_regular_user_cannot_transition_to_non_allowed_status(): void
    {
        $user = UserModel::factory()->create();
        $this->actingAs($user);

        $order = OrderModel::factory()->create([
            'user_id' => $user->id,
            'status' => OrderStatus::Pending->value,
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
            'status' => OrderStatus::Shipped->value,  // not allowed for users
            'total_price' => 100,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 100],
            ],
        ];

        $response = $this->putJson(route('v1.orders.update', $order->id), $payload);

        $response->assertStatus(Response::HTTP_BAD_REQUEST);
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => OrderStatus::Pending->value]);
    }

    public function test_regular_user_cannot_update_other_users_order(): void
    {
        $user = UserModel::factory()->create();
        $other = UserModel::factory()->create();
        $this->actingAs($user);

        $order = OrderModel::factory()->create([
            'user_id' => $other->id,
            'status' => OrderStatus::Pending->value,
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
            'status' => OrderStatus::Cancelled->value,
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
