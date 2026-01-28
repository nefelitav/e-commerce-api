<?php

namespace Tests\Feature\Controllers;

use App\Models\Cart\CartModel;
use App\Models\Product\ProductModel;
use App\Models\UserModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class CreateCartControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_cart_successfully(): void
    {
        $user = UserModel::factory()->create();
        $this->actingAs($user);

        $product = ProductModel::factory()->create();

        $payload = [
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 2,
                ],
            ],
        ];

        $response = $this->postJson(route('carts.store'), $payload);

        $response
            ->assertStatus(Response::HTTP_CREATED)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'user_id',
                    'items',
                ],
            ]);

        $cartId = $response->json('data.id');

        $this->assertDatabaseHas('carts', [
            'id' => $cartId,
            'user_id' => $user->id,
        ]);

        $this->assertDatabaseHas('cart_items', [
            'cart_id' => $cartId,
            'product_id' => $product->id,
            'quantity' => 2,
        ]);
    }

    public function test_create_cart_fails_validation(): void
    {
        $user = UserModel::factory()->create();
        $this->actingAs($user);

        $payload = [
            'items' => [
                [
                    'product_id' => 999,
                    'quantity' => 0,
                ],
            ],
        ];

        $response = $this->postJson(route('carts.store'), $payload);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
        $response->assertJsonValidationErrors(['items.0.product_id', 'items.0.quantity']);
    }
}
