<?php

namespace Tests\Feature\Controllers\Product;

use App\Models\Category\CategoryModel;
use App\Models\Product\ProductModel;
use App\Models\UserModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response;
use Tests\TestCase;

class UpdateProductControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_returns_401(): void
    {
        $product = ProductModel::factory()->create();
        $response = $this->putJson(route('v1.products.update', $product->id), []);
        $response->assertStatus(Response::HTTP_UNAUTHORIZED);
    }

    public function test_regular_user_returns_403(): void
    {
        $user = UserModel::factory()->create();
        $this->actingAs($user);

        $product = ProductModel::factory()->create();
        $response = $this->putJson(route('v1.products.update', $product->id), []);
        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    public function test_update_product_successfully(): void
    {
        $admin = UserModel::factory()->admin()->create();
        $this->actingAs($admin);

        $category = CategoryModel::factory()->create();
        $product = ProductModel::factory()->create([
            'name' => 'Old Product Name',
            'description' => 'Old Product Description',
            'price' => 1999,
            'quantity' => 10,
            'category_id' => $category->id,
        ]);

        $payload = [
            'id' => $product->id,
            'name' => 'Updated Product Name',
            'description' => 'New Product Description',
            'price' => 1999,
            'quantity' => 10,
            'category_id' => $category->id,
        ];

        $response = $this->putJson(route('v1.products.update', $product->id), $payload);

        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonFragment([
                'name' => 'Updated Product Name',
                'description' => 'New Product Description',
            ]);

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'name' => 'Updated Product Name',
        ]);
    }

    public function test_update_product_fails_with_invalid_data(): void
    {
        $admin = UserModel::factory()->admin()->create();
        $this->actingAs($admin);

        $product = ProductModel::factory()->create();

        $response = $this->putJson(route('v1.products.update', $product->id), [
            'name' => '',
        ]);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonValidationErrors(['name']);
    }
}
