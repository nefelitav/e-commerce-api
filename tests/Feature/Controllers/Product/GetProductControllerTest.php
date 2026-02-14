<?php

namespace Tests\Feature\Controllers\Product;

use App\Models\Product\ProductModel;
use App\Models\UserModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class GetProductControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_show_returns_successful_response(): void
    {
        $user = UserModel::factory()->create();
        $this->actingAs($user);

        $product = ProductModel::factory()->create();

        $response = $this->getJson(route('v1.products.show', ['id' => $product->id]));

        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                ],
            ]);

        $this->assertEquals($product->id, $response->json('data.id'));
    }

    public function test_show_returns_validation_error_for_nonexistent_product(): void
    {
        $user = UserModel::factory()->create();
        $this->actingAs($user);

        $response = $this->getJson(route('v1.products.show', ['id' => 999999]));

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
        $response->assertJsonValidationErrors('id');
    }
}
