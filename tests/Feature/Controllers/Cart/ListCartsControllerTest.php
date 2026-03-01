<?php

namespace Tests\Feature\Controllers\Cart;

use App\Models\Cart\CartModel;
use App\Models\UserModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class ListCartsControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_returns_401(): void
    {
        $response = $this->getJson(route('v1.carts.index'));
        $response->assertStatus(Response::HTTP_UNAUTHORIZED);
    }

    public function test_regular_user_returns_403(): void
    {
        $user = UserModel::factory()->create();
        $this->actingAs($user);

        $response = $this->getJson(route('v1.carts.index'));
        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    public function test_admin_can_list_carts(): void
    {
        $admin = UserModel::factory()->admin()->create();
        $this->actingAs($admin);

        CartModel::factory()->count(3)->create();

        $response = $this->getJson(route('v1.carts.index'));

        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => ['id', 'user_id'],
                ],
            ]);
    }
}
