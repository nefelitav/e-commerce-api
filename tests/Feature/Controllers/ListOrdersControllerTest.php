<?php

namespace Tests\Feature\Controllers;

use App\Models\Order\OrderModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class ListOrdersControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_returns_list_of_orders(): void
    {
        OrderModel::factory()->count(3)->create();

        $response = $this->getJson(route('orders.index'));

        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id',
                        'status',
                    ],
                ],
            ]);
    }
}

