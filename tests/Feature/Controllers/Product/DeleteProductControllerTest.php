<?php

namespace Tests\Feature\Controllers\Product;

use App\Models\Product\ProductModel;
use App\Models\UserModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class DeleteProductControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_returns_401(): void
    {
        $product = ProductModel::factory()->create();
        $response = $this->deleteJson(route('v1.products.destroy', ['id' => $product->id]));
        $response->assertStatus(Response::HTTP_UNAUTHORIZED);
    }

    public function test_regular_user_returns_403(): void
    {
        $user = UserModel::factory()->create();
        $this->actingAs($user);

        $product = ProductModel::factory()->create();
        $response = $this->deleteJson(route('v1.products.destroy', ['id' => $product->id]));
        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    public function test_admin_can_delete_product(): void
    {
        $admin = UserModel::factory()->admin()->create();
        $this->actingAs($admin);

        $product = ProductModel::factory()->create();

        $response = $this->deleteJson(route('v1.products.destroy', ['id' => $product->id]));

        $response->assertStatus(Response::HTTP_NO_CONTENT);
        $this->assertDatabaseMissing('products', ['id' => $product->id]);
    }

    public function test_delete_nonexistent_product_returns_422(): void
    {
        $admin = UserModel::factory()->admin()->create();
        $this->actingAs($admin);

        $response = $this->deleteJson(route('v1.products.destroy', ['id' => 999999]));

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }
}


