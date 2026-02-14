<?php

namespace Tests\Feature\Controllers\Cart;

use App\Models\Cart\CartModel;
use App\Models\UserModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class GetCartControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_show_returns_successful_response(): void
    {
        $user = UserModel::factory()->create();
        $this->actingAs($user);

        $cart = CartModel::factory()->create();

        $response = $this->getJson(route('v1.carts.show', ['id' => $cart->id]));

        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                ],
            ]);

        $this->assertEquals($cart->id, $response->json('data.id'));
    }

    public function test_show_returns_validation_error_for_nonexistent_cart(): void
    {
        $user = UserModel::factory()->create();
        $this->actingAs($user);

        $response = $this->getJson(route('v1.carts.show', ['id' => 999999]));

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
        $response->assertJsonValidationErrors('id');
    }
}
