<?php

namespace Tests\Feature\Controllers;

use App\Models\Order\OrderItemModel;
use App\Models\Order\OrderModel;
use App\Models\Product\ProductModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response;
use Tests\TestCase;

class UpdateOrderControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_update_order_successfully(): void
    {
        $order = OrderModel::factory()->create([
            'status' => 'pending',
            'total_price' => 100,
        ]);

        $oldProduct = ProductModel::factory()->create();
        OrderItemModel::factory()->create([
            'order_id' => $order->id,
            'product_id' => $oldProduct->id,
            'quantity' => 1,
            'unit_price' => 100,
        ]);

        $newProduct = ProductModel::factory()->create();

        $payload = [
            'id' => $order->id,
            'status' => 'paid',
            'total_price' => 200,
            'items' => [
                [
                    'product_id' => $newProduct->id,
                    'quantity' => 2,
                    'unit_price' => 100,
                ],
            ],
        ];

        $response = $this->putJson(route('orders.update', $order->id), $payload);

        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonFragment([
                'status' => 'paid',
                'total_price' => 200,
            ]);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'paid',
            'total_price' => 200,
        ]);

        $this->assertDatabaseMissing('order_items', [
            'order_id' => $order->id,
            'product_id' => $oldProduct->id,
        ]);

        $this->assertDatabaseHas('order_items', [
            'order_id' => $order->id,
            'product_id' => $newProduct->id,
            'quantity' => 2,
            'unit_price' => 100,
        ]);
    }

    public function test_update_order_fails_with_invalid_data(): void
    {
        $order = OrderModel::factory()->create();

        $payload = [
            'status' => '',
            'total_price' => 100,
        ];

        $response = $this->putJson(route('orders.update', $order->id), $payload);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonValidationErrors(['status']);
    }
}

