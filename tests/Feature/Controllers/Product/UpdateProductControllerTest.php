<?php

namespace Tests\Feature\Controllers\Product;

use App\Models\Category\CategoryModel;
use App\Models\Product\ProductModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response;
use Tests\TestCase;

class UpdateProductControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_update_product_successfully(): void
    {
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
                'price' => 1999,
                'quantity' => 10,
                'category_id' => $category->id,
            ]);

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'name' => 'Updated Product Name',
            'description' => 'New Product Description',
            'price' => 1999,
            'quantity' => 10,
            'category_id' => $category->id,
        ]);
    }

    public function test_update_product_with_parent_successfully(): void
    {
        $category = CategoryModel::factory()->create();

        $childProduct = ProductModel::factory()->create([
            'name' => 'Child Product',
            'description' => 'Old Product Description',
            'price' => 1999,
            'quantity' => 10,
            'category_id' => $category->id,
        ]);

        $payload = [
            'id' => $childProduct->id,
            'name' => 'Updated Child Product',
            'price' => 1999,
            'quantity' => 10,
            'category_id' => $category->id,
            'description' => 'New Product Description',
        ];

        $response = $this->putJson(route('v1.products.update', $childProduct->id), $payload);

        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonFragment([
                'name' => 'Updated Child Product',
                'price' => 1999,
                'quantity' => 10,
                'category_id' => $category->id,
                'description' => 'New Product Description',
            ]);

        $this->assertDatabaseHas('products', [
            'id' => $childProduct->id,
            'name' => 'Updated Child Product',
            'price' => 1999,
            'quantity' => 10,
            'category_id' => $category->id,
            'description' => 'New Product Description',
        ]);
    }

    public function test_update_product_fails_with_invalid_data(): void
    {
        $product = ProductModel::factory()->create();

        $payload = [
            'parent_id' => null,
            'name' => '',
        ];

        $response = $this->putJson(route('v1.products.update', $product->id), $payload);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonValidationErrors(['name']);
    }
}
