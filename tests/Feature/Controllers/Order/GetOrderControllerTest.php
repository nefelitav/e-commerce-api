<?php

namespace Tests\Feature\Controllers\Order;

use App\Models\Order\OrderModel;
use App\Models\UserModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class GetOrderControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_returns_401(): void
    {
        $order = OrderModel::factory()->create();
        $response = $this->getJson(route('v1.orders.show', ['id' => $order->id]));
        $response->assertStatus(Response::HTTP_UNAUTHORIZED);
    }

    public function test_show_returns_own_order(): void
    {
        $user = UserModel::factory()->create();
        $this->actingAs($user);

        $order = OrderModel::factory()->create(['user_id' => $user->id]);

        $response = $this->getJson(route('v1.orders.show', ['id' => $order->id]));

        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonStructure(['success', 'data' => ['id']]);

        $this->assertEquals($order->id, $response->json('data.id'));
    }

    public function test_regular_user_cannot_see_other_users_order(): void
    {
        $user = UserModel::factory()->create();
        $other = UserModel::factory()->create();
        $this->actingAs($user);

        $order = OrderModel::factory()->create(['user_id' => $other->id]);

        $response = $this->getJson(route('v1.orders.show', ['id' => $order->id]));

        $response->assertStatus(Response::HTTP_BAD_REQUEST);
    }

    public function test_admin_can_see_any_order(): void
    {
        $admin = UserModel::factory()->admin()->create();
        $user = UserModel::factory()->create();
        $this->actingAs($admin);

        $order = OrderModel::factory()->create(['user_id' => $user->id]);

        $response = $this->getJson(route('v1.orders.show', ['id' => $order->id]));

        $response->assertStatus(Response::HTTP_OK);
        $this->assertEquals($order->id, $response->json('data.id'));
    }

    public function test_show_returns_validation_error_for_nonexistent_order(): void
    {
        $user = UserModel::factory()->create();
        $this->actingAs($user);

        $response = $this->getJson(route('v1.orders.show', ['id' => 999999]));

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
        $response->assertJsonValidationErrors('id');
    }
}

