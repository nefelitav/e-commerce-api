<?php

namespace Tests\Feature\Controllers\Product;

use App\Models\Category\CategoryModel;
use App\Models\Product\ProductModel;
use App\Models\UserModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class ListCategoryProductsControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_products_returned_for_category(): void
    {
        $user = UserModel::factory()->create();
        $this->actingAs($user);

        $products = ProductModel::factory()->count(3)->create();

        $categoryId = $products->first()->category_id;

        $response = $this->getJson(route('v1.categories.products.index', ['id' => $categoryId]));

        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => ['id', 'name', 'description', 'price', 'quantity'],
                ],
            ]);

        $data = $response->json('data');

        foreach ($data as $product) {
            $this->assertEquals($categoryId, $product['category_id']);
        }
    }

    public function test_returns_empty_for_category_with_no_products(): void
    {
        $user = UserModel::factory()->create();
        $this->actingAs($user);

        $category = CategoryModel::factory()->create();

        $response = $this->getJson(route('v1.categories.products.index', ['id' => $category->id]));

        $response->assertStatus(Response::HTTP_OK)
            ->assertJson([
                'success' => true,
                'data' => [],
            ]);
    }

    public function test_subproducts_returns_404_for_nonexistent_product(): void
    {
        $user = UserModel::factory()->create();
        $this->actingAs($user);

        $response = $this->getJson(route('v1.categories.products.index', ['id' => 999999]));

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }
}
