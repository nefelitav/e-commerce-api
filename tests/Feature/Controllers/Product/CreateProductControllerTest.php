<?php

namespace Tests\Feature\Controllers\Product;

use App\Models\Category\CategoryModel;
use App\Models\Product\ProductModel;
use App\Models\UserModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class CreateProductControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_returns_401(): void
    {
        $response = $this->postJson(route('v1.products.store'), []);
        $response->assertStatus(Response::HTTP_UNAUTHORIZED);
    }

    public function test_regular_user_returns_403(): void
    {
        $user = UserModel::factory()->create();
        $this->actingAs($user);

        $response = $this->postJson(route('v1.products.store'), []);
        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    public function test_create_product_successfully(): void
    {
        $admin = UserModel::factory()->admin()->create();
        $this->actingAs($admin);

        $category = CategoryModel::factory()->create();

        $payload = [
            'name' => 'Test Product',
            'description' => 'A test product description',
            'price' => 1999,
            'quantity' => 10,
            'category_id' => $category->id,
        ];

        $response = $this->postJson(route('v1.products.store'), $payload);

        $response
            ->assertStatus(Response::HTTP_CREATED)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'name',
                    'description',
                    'price',
                    'quantity',
                    'category_id',
                ],
            ]);

        $this->assertDatabaseHas('products', [
            'name' => 'Test Product',
            'description' => 'A test product description',
            'price' => 1999,
            'quantity' => 10,
            'category_id' => $category->id,
        ]);
    }

    public function test_create_product_fails_validation(): void
    {
        $admin = UserModel::factory()->admin()->create();
        $this->actingAs($admin);

        $payload = [
            'price' => 1000,
        ];

        $response = $this->postJson(route('v1.products.store'), $payload);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
        $response->assertJsonValidationErrors('name');
    }

    public function test_create_product_fails_when_product_already_exists(): void
    {
        $admin = UserModel::factory()->admin()->create();
        $this->actingAs($admin);

        $category = CategoryModel::factory()->create();

        ProductModel::factory()->create([
            'name' => 'Existing Product',
        ]);

        $payload = [
            'name' => 'Existing Product',
            'description' => 'Duplicate product',
            'price' => 999,
            'quantity' => 10,
            'category_id' => $category->id,
        ];

        $response = $this->postJson(route('v1.products.store'), $payload);

        $response->assertStatus(Response::HTTP_BAD_REQUEST);

        $this->assertDatabaseCount('products', 1);
    }
}
