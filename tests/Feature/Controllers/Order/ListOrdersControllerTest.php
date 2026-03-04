<?php

namespace Tests\Feature\Controllers\Order;

use App\Models\Order\OrderModel;
use App\Models\UserModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class ListOrdersControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_returns_401(): void
    {
        $response = $this->getJson(route('v1.orders.index'));
        $response->assertStatus(Response::HTTP_UNAUTHORIZED);
    }

    public function test_admin_sees_all_orders(): void
    {
        $admin = UserModel::factory()->admin()->create();
        $this->actingAs($admin);

        OrderModel::factory()->count(3)->create();

        $response = $this->getJson(route('v1.orders.index'));

        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonStructure([
                'success',
                'data' => ['*' => ['id', 'status']],
                'meta' => ['current_page', 'per_page', 'total', 'last_page'],
            ]);

        $this->assertEquals(3, $response->json('meta.total'));
    }

    public function test_regular_user_only_sees_own_orders(): void
    {
        $user = UserModel::factory()->create();
        $other = UserModel::factory()->create();
        $this->actingAs($user);

        OrderModel::factory()->count(2)->create(['user_id' => $user->id]);
        OrderModel::factory()->count(3)->create(['user_id' => $other->id]);

        $response = $this->getJson(route('v1.orders.index'));

        $response->assertStatus(Response::HTTP_OK);
        $this->assertEquals(2, $response->json('meta.total'));

        foreach ($response->json('data') as $order) {
            $this->assertEquals($user->id, $order['user_id']);
        }
    }

    public function test_list_orders_with_pagination(): void
    {
        $admin = UserModel::factory()->admin()->create();
        $this->actingAs($admin);

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
        $admin = UserModel::factory()->admin()->create();
        $this->actingAs($admin);

        OrderModel::factory()->create(['total_price' => 100.00]);
        OrderModel::factory()->create(['total_price' => 50.00]);
        OrderModel::factory()->create(['total_price' => 75.00]);

        $response = $this->getJson(route('v1.orders.index', [
            'sort' => 'total_price',
            'order' => 'desc',
        ]));

        $response->assertStatus(Response::HTTP_OK);
        $prices = array_column($response->json('data'), 'total_price');
        $this->assertEquals([100, 75, 50], $prices);
    }

    public function test_filter_by_single_status(): void
    {
        $admin = UserModel::factory()->admin()->create();
        $this->actingAs($admin);

        OrderModel::factory()->create(['status' => 'pending']);
        OrderModel::factory()->create(['status' => 'paid']);
        OrderModel::factory()->create(['status' => 'shipped']);

        $response = $this->getJson(route('v1.orders.index', [
            'filter' => ['status' => 'pending'],
        ]));

        $response->assertStatus(Response::HTTP_OK);
        $this->assertEquals(1, $response->json('meta.total'));
        $this->assertEquals('pending', $response->json('data.0.status'));
    }

    public function test_filter_by_multiple_statuses(): void
    {
        $admin = UserModel::factory()->admin()->create();
        $this->actingAs($admin);

        OrderModel::factory()->create(['status' => 'pending']);
        OrderModel::factory()->create(['status' => 'paid']);
        OrderModel::factory()->create(['status' => 'shipped']);
        OrderModel::factory()->create(['status' => 'delivered']);

        $response = $this->getJson(route('v1.orders.index', [
            'filter' => ['status' => 'pending,paid'],
        ]));

        $response->assertStatus(Response::HTTP_OK);
        $this->assertEquals(2, $response->json('meta.total'));

        $statuses = array_column($response->json('data'), 'status');
        $this->assertContains('pending', $statuses);
        $this->assertContains('paid', $statuses);
        $this->assertNotContains('shipped', $statuses);
    }

    public function test_filter_by_multiple_statuses_validation_rejects_invalid(): void
    {
        $admin = UserModel::factory()->admin()->create();
        $this->actingAs($admin);

        $response = $this->getJson(route('v1.orders.index', [
            'filter' => ['status' => 'pending,invalid_status'],
        ]));

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }
}

