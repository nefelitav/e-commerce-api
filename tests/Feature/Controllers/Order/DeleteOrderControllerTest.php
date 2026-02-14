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

    public function test_delete_order_successfully(): void
    {
        $user = UserModel::factory()->create();
        $this->actingAs($user);

        $order = OrderModel::factory()->create([
            'user_id' => $user->id,
        ]);

        $product = ProductModel::factory()->create();

        OrderItemModel::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
        ]);

        $response = $this->deleteJson(route('v1.orders.destroy', ['id' => $order->id]));

        $response->assertStatus(Response::HTTP_NO_CONTENT);

        $this->assertDatabaseMissing('orders', ['id' => $order->id]);
        $this->assertDatabaseMissing('order_items', ['order_id' => $order->id]);
    }

    public function test_delete_nonexistent_order_returns_422(): void
    {
        $user = UserModel::factory()->create();
        $this->actingAs($user);

        $response = $this->deleteJson(route('v1.orders.destroy', ['id' => 999999]));

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }
}

