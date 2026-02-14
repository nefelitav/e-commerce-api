<?php

namespace Tests\Feature\Controllers\Cart;

use App\Models\Cart\CartItemModel;
use App\Models\Cart\CartModel;
use App\Models\Product\ProductModel;
use App\Models\UserModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response;
use Tests\TestCase;

class UpdateCartControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_update_cart_successfully(): void
    {
        $user = UserModel::factory()->create();
        $cart = CartModel::factory()->create([
            'user_id' => $user->id,
        ]);

        $product = ProductModel::factory()->create();

        $payload = [
            'id' => $cart->id,
            'user_id' => $user->id,
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 3,
                ],
            ],
        ];

        $response = $this->putJson(route('v1.carts.update', $cart->id), $payload);

        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonFragment([
                'id' => $cart->id,
                'user_id' => $user->id,
            ]);

        $this->assertDatabaseHas('carts', [
            'id' => $cart->id,
            'user_id' => $user->id,
        ]);

        $this->assertDatabaseHas('cart_items', [
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'quantity' => 3,
        ]);
    }

    public function test_update_cart_replaces_existing_items(): void
    {
        $user = UserModel::factory()->create();
        $cart = CartModel::factory()->create([
            'user_id' => $user->id,
        ]);

        $oldProduct = ProductModel::factory()->create();
        $oldItem = CartItemModel::factory()->create([
            'cart_id' => $cart->id,
            'product_id' => $oldProduct->id,
            'quantity' => 1,
        ]);

        $newProduct = ProductModel::factory()->create();

        $payload = [
            'id' => $cart->id,
            'user_id' => $user->id,
            'items' => [
                [
                    'product_id' => $newProduct->id,
                    'quantity' => 5,
                ],
            ],
        ];

        $response = $this->putJson(route('v1.carts.update', $cart->id), $payload);

        $response->assertStatus(Response::HTTP_OK);

        $this->assertDatabaseMissing('cart_items', [
            'id' => $oldItem->id,
        ]);

        $this->assertDatabaseHas('cart_items', [
            'cart_id' => $cart->id,
            'product_id' => $newProduct->id,
            'quantity' => 5,
        ]);
    }

    public function test_update_cart_fails_with_invalid_data(): void
    {
        $cart = CartModel::factory()->create();

        $payload = [
            'id' => $cart->id,
            'items' => [
                [
                    'product_id' => '',
                    'quantity' => -1,
                ],
            ],
        ];

        $response = $this->putJson(route('v1.carts.update', $cart->id), $payload);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonValidationErrors(['items.0.product_id', 'items.0.quantity']);
    }
}
