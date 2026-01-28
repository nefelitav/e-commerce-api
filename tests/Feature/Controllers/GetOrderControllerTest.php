<?php

namespace Tests\Feature\Controllers;

use App\Models\Order\OrderModel;
use App\Models\UserModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class GetOrderControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_show_returns_successful_response(): void
    {
        $user = UserModel::factory()->create();
        $this->actingAs($user);

        $order = OrderModel::factory()->create([
            'user_id' => $user->id,
        ]);

        $response = $this->getJson(route('orders.show', ['id' => $order->id]));

        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                ],
            ]);

        $this->assertEquals($order->id, $response->json('data.id'));
    }

    public function test_show_returns_validation_error_for_nonexistent_order(): void
    {
        $user = UserModel::factory()->create();
        $this->actingAs($user);

        $response = $this->getJson(route('orders.show', ['id' => 999999]));

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
        $response->assertJsonValidationErrors('id');
    }
}

