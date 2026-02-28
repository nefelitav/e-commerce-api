<?php

namespace Tests\Feature\Controllers\Order;

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

        $response = $this->getJson(route('v1.orders.index'));

        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id',
                        'status',
                    ],
                ],
                'meta' => [
                    'current_page',
                    'per_page',
                    'total',
                    'last_page',
                ],
            ]);
    }

    public function test_list_orders_with_pagination(): void
    {
        OrderModel::factory()->count(30)->create();

        $response = $this->getJson(route('v1.orders.index', [
            'page' => 2,
            'per_page' => 10,
        ]));

        $response->assertStatus(Response::HTTP_OK);
        $json = $response->json();

        $this->assertEquals(2, $json['meta']['current_page']);
        $this->assertEquals(10, $json['meta']['per_page']);
        $this->assertEquals(30, $json['meta']['total']);
        $this->assertEquals(3, $json['meta']['last_page']);
        $this->assertCount(10, $json['data']);
    }

    public function test_list_orders_with_sorting_by_total_price(): void
    {
        OrderModel::factory()->create(['total_price' => 100.00]);
        OrderModel::factory()->create(['total_price' => 50.00]);
        OrderModel::factory()->create(['total_price' => 75.00]);

        $response = $this->getJson(route('v1.orders.index', [
            'sort' => 'total_price',
            'order' => 'desc',
        ]));

        $response->assertStatus(Response::HTTP_OK);
        $json = $response->json();

        $prices = array_column($json['data'], 'total_price');
        $this->assertEquals([100, 75, 50], $prices);
    }
}

