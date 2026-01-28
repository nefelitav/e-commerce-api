<?php

namespace Tests\Feature\Controllers;

use App\Models\Product\ProductModel;
use App\Models\UserModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class DeleteProductControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_delete_product_successfully(): void
    {
        $user = UserModel::factory()->create();
        $this->actingAs($user);

        $product = ProductModel::factory()->create();

        $response = $this->deleteJson(route('products.destroy', ['id' => $product->id]));

        $response->assertStatus(Response::HTTP_NO_CONTENT);

        $this->assertDatabaseMissing('products', ['id' => $product->id]);
    }

    public function test_delete_nonexistent_product_returns_404(): void
    {
        $user = UserModel::factory()->create();
        $this->actingAs($user);

        $response = $this->deleteJson(route('products.destroy', ['id' => 999999]));

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }
}
