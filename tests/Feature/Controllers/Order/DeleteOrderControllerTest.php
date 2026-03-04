<?php

namespace Tests\Feature\Controllers\Order;

use App\Models\Order\OrderItemModel;
use App\Models\Order\OrderModel;
use App\Models\Product\ProductModel;
use App\Models\UserModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class DeleteOrderControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_returns_401(): void
    {
        $order = OrderModel::factory()->create();
        $response = $this->deleteJson(route('v1.orders.destroy', ['id' => $order->id]));
        $response->assertStatus(Response::HTTP_UNAUTHORIZED);
    }

    public function test_regular_user_returns_403(): void
    {
        $user = UserModel::factory()->create();
        $this->actingAs($user);

        $order = OrderModel::factory()->create(['user_id' => $user->id]);

        $response = $this->deleteJson(route('v1.orders.destroy', ['id' => $order->id]));
        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    public function test_admin_can_delete_order(): void
    {
        $admin = UserModel::factory()->admin()->create();
        $this->actingAs($admin);

        $order = OrderModel::factory()->create();
        $product = ProductModel::factory()->create(['quantity' => 7]);

        OrderItemModel::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 3,
        ]);

        $response = $this->deleteJson(route('v1.orders.destroy', ['id' => $order->id]));

        $response->assertStatus(Response::HTTP_NO_CONTENT);
        $this->assertDatabaseMissing('orders', ['id' => $order->id]);
        $this->assertDatabaseMissing('order_items', ['order_id' => $order->id]);

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'quantity' => 10,
        ]);

        $this->assertDatabaseHas('inventory_history', [
            'product_id' => $product->id,
            'change_type' => 'return',
            'quantity_changed' => 3,
            'previous_quantity' => 7,
            'new_quantity' => 10,
        ]);
    }

    public function test_delete_nonexistent_order_returns_422(): void
    {
        $admin = UserModel::factory()->admin()->create();
        $this->actingAs($admin);

        $response = $this->deleteJson(route('v1.orders.destroy', ['id' => 999999]));

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }
}

