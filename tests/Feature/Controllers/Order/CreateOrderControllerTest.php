<?php

namespace Tests\Feature\Controllers\Order;

use App\Models\Product\ProductModel;
use App\Models\UserModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class CreateOrderControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_returns_401(): void
    {
        $response = $this->postJson(route('v1.orders.store'), []);
        $response->assertStatus(Response::HTTP_UNAUTHORIZED);
    }

    public function test_create_order_successfully(): void
    {
        $user = UserModel::factory()->create();
        $this->actingAs($user);

        $product = ProductModel::factory()->create([
            'price' => 1999,
            'quantity' => 10,
        ]);

        $payload = [
            'status' => 'pending',
            'total_price' => 1999,
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 1,
                    'unit_price' => 1999,
                ],
            ],
        ];

        $response = $this->postJson(route('v1.orders.store'), $payload);

        $response
            ->assertStatus(Response::HTTP_CREATED)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'user_id',
                    'status',
                    'total_price',
                    'items',
                ],
            ]);

        $orderId = $response->json('data.id');

        $this->assertDatabaseHas('orders', [
            'id' => $orderId,
            'user_id' => $user->id,
            'status' => 'pending',
            'total_price' => 1999,
        ]);

        $this->assertDatabaseHas('order_items', [
            'order_id' => $orderId,
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 1999,
        ]);
    }

    public function test_create_order_decrements_product_stock(): void
    {
        $user = UserModel::factory()->create();
        $this->actingAs($user);

        $product = ProductModel::factory()->create([
            'price' => 500,
            'quantity' => 8,
        ]);

        $payload = [
            'status' => 'pending',
            'total_price' => 1500,
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 3,
                    'unit_price' => 500,
                ],
            ],
        ];

        $this->postJson(route('v1.orders.store'), $payload)
            ->assertStatus(Response::HTTP_CREATED);

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'quantity' => 5,
        ]);

        $this->assertDatabaseHas('inventory_history', [
            'product_id' => $product->id,
            'change_type' => 'sale',
            'previous_quantity' => 8,
            'new_quantity' => 5,
            'quantity_changed' => -3,
        ]);
    }

    public function test_create_order_fails_when_insufficient_stock(): void
    {
        $user = UserModel::factory()->create();
        $this->actingAs($user);

        $product = ProductModel::factory()->create([
            'price' => 500,
            'quantity' => 2,
        ]);

        $payload = [
            'status' => 'pending',
            'total_price' => 2500,
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 5,
                    'unit_price' => 500,
                ],
            ],
        ];

        $this->postJson(route('v1.orders.store'), $payload)
            ->assertStatus(Response::HTTP_BAD_REQUEST);

        // Stock must not have changed
        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'quantity' => 2,
        ]);

        $this->assertDatabaseMissing('orders', ['user_id' => $user->id]);
    }

    public function test_create_order_fails_validation(): void
    {
        $user = UserModel::factory()->create();
        $this->actingAs($user);

        $payload = [
            'total_price' => 1000,
        ];

        $response = $this->postJson(route('v1.orders.store'), $payload);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
        $response->assertJsonValidationErrors('status');
    }
}

