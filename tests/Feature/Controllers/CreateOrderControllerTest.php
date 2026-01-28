<?php

namespace Tests\Feature\Controllers;

use App\Models\Product\ProductModel;
use App\Models\UserModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class CreateOrderControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_order_successfully(): void
    {
        $user = UserModel::factory()->create();
        $this->actingAs($user);

        $product = ProductModel::factory()->create([
            'price' => 1999,
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

        $response = $this->postJson(route('orders.store'), $payload);

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

    public function test_create_order_fails_validation(): void
    {
        $user = UserModel::factory()->create();
        $this->actingAs($user);

        $payload = [
            'total_price' => 1000,
        ];

        $response = $this->postJson(route('orders.store'), $payload);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
        $response->assertJsonValidationErrors('status');
    }
}

