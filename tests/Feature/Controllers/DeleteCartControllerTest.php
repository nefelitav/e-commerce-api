<?php

namespace Tests\Feature\Controllers;

use App\Models\Cart\CartItemModel;
use App\Models\Cart\CartModel;
use App\Models\UserModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class DeleteCartControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_delete_cart_successfully(): void
    {
        $user = UserModel::factory()->create();
        $this->actingAs($user);

        $cart = CartModel::factory()->create();
        CartItemModel::factory()->create([
            'cart_id' => $cart->id,
        ]);

        $response = $this->deleteJson(route('carts.destroy', ['id' => $cart->id]));

        $response->assertStatus(Response::HTTP_NO_CONTENT);

        $this->assertDatabaseMissing('carts', ['id' => $cart->id]);
        $this->assertDatabaseMissing('cart_items', ['cart_id' => $cart->id]);
    }

    public function test_delete_nonexistent_cart_returns_error(): void
    {
        $user = UserModel::factory()->create();
        $this->actingAs($user);

        $response = $this->deleteJson(route('carts.destroy', ['id' => 999999]));

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }
}
