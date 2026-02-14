<?php

namespace Tests\Feature\Controllers\Cart;

use App\Models\Cart\CartModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class ListCartsControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_returns_list_of_carts(): void
    {
        CartModel::factory()->count(3)->create();

        $response = $this->getJson(route('v1.carts.index'));

        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id',
                        'user_id',
                    ],
                ],
            ]);
    }
}
