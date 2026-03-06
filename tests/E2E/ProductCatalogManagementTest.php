<?php

namespace Tests\E2E;

use App\Models\Category\CategoryModel;
use App\Models\UserModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class ProductCatalogManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_full_catalog_setup_with_nested_categories_and_products(): void
    {
        $admin = UserModel::factory()->admin()->create();
        $this->actingAs($admin);

        $parentCategoryResponse = $this->postJson(route('v1.categories.store'), [
            'name' => 'Clothing',
            'description' => 'All clothing items',
            'parent_id' => null,
        ]);
        $parentCategoryResponse->assertStatus(Response::HTTP_CREATED);
        $parentCategoryId = $parentCategoryResponse->json('data.id');

        $menResponse = $this->postJson(route('v1.categories.store'), [
            'name' => 'Men',
            'description' => 'Clothing for men',
            'parent_id' => $parentCategoryId,
        ]);
        $menResponse->assertStatus(Response::HTTP_CREATED);
        $menCategoryId = $menResponse->json('data.id');

        $womenResponse = $this->postJson(route('v1.categories.store'), [
            'name' => 'Women',
            'description' => 'Clothing for women',
            'parent_id' => $parentCategoryId,
        ]);
        $womenResponse->assertStatus(Response::HTTP_CREATED);
        $womenCategoryId = $womenResponse->json('data.id');

        $this->getJson(route('v1.categories.subcategories', $parentCategoryId))
            ->assertStatus(Response::HTTP_OK);

        $shirtResponse = $this->postJson(route('v1.products.store'), [
            'name' => 'Cotton T-Shirt',
            'description' => 'Comfortable cotton t-shirt',
            'price' => 29.99,
            'quantity' => 100,
            'category_id' => $menCategoryId,
        ]);
        $shirtResponse->assertStatus(Response::HTTP_CREATED);
        $shirtId = $shirtResponse->json('data.id');

        $this->postJson(route('v1.products.store'), [
            'name' => 'Summer Dress',
            'description' => 'Light summer dress',
            'price' => 59.99,
            'quantity' => 50,
            'category_id' => $womenCategoryId,
        ])->assertStatus(Response::HTTP_CREATED);

        $guest = UserModel::factory()->create();
        $this->actingAs($guest);

        $this->getJson(route('v1.products.index'))->assertStatus(Response::HTTP_OK);
        $this->getJson(route('v1.categories.index'))->assertStatus(Response::HTTP_OK);
        $this->getJson(route('v1.categories.show', $menCategoryId))
            ->assertStatus(Response::HTTP_OK)
            ->assertJsonFragment(['name' => 'Men']);

        $this->actingAs($admin);

        $this->putJson(route('v1.products.update', $shirtId), [
            'name' => 'Premium Cotton T-Shirt',
            'description' => 'Premium quality comfortable cotton t-shirt',
            'price' => 34.99,
            'quantity' => 100,
            'category_id' => $menCategoryId,
        ])->assertStatus(Response::HTTP_OK)
            ->assertJsonFragment(['name' => 'Premium Cotton T-Shirt']);

        $this->assertDatabaseHas('products', [
            'id' => $shirtId,
            'name' => 'Premium Cotton T-Shirt',
            'price' => 34.99,
        ]);
    }

    public function test_admin_creates_product_customer_orders_then_views_inventory(): void
    {
        $admin = UserModel::factory()->admin()->create();
        $customer = UserModel::factory()->create();

        $this->actingAs($admin);
        $category = CategoryModel::factory()->create(['name' => 'Gadgets']);

        $productResponse = $this->postJson(route('v1.products.store'), [
            'name' => 'Portable Charger',
            'price' => 39.99,
            'quantity' => 20,
            'category_id' => $category->id,
        ]);
        $productResponse->assertStatus(Response::HTTP_CREATED);
        $productId = $productResponse->json('data.id');

        $this->actingAs($customer);
        $this->postJson(route('v1.orders.store'), [
            'status' => 'pending',
            'total_price' => 79.98,
            'items' => [
                ['product_id' => $productId, 'quantity' => 2, 'unit_price' => 39.99],
            ],
        ])->assertStatus(Response::HTTP_CREATED);

        $this->assertDatabaseHas('products', ['id' => $productId, 'quantity' => 18]);

        // Verify inventory history was tracked
        $this->actingAs($admin);
        $this->getJson(route('v1.products.inventory-history.index', $productId))
            ->assertStatus(Response::HTTP_OK);

        $this->assertDatabaseHas('inventory_history', [
            'product_id' => $productId,
            'change_type' => 'sale',
            'quantity_changed' => -2,
        ]);
    }

    public function test_category_hierarchy_browsing(): void
    {
        $admin = UserModel::factory()->admin()->create();
        $this->actingAs($admin);

        $electronicsResponse = $this->postJson(route('v1.categories.store'), [
            'name' => 'Electronics',
            'description' => 'All electronics',
            'parent_id' => null,
        ]);
        $electronicsId = $electronicsResponse->json('data.id');

        $phonesResponse = $this->postJson(route('v1.categories.store'), [
            'name' => 'Phones',
            'description' => 'Mobile phones',
            'parent_id' => $electronicsId,
        ]);
        $phonesId = $phonesResponse->json('data.id');

        $laptopsResponse = $this->postJson(route('v1.categories.store'), [
            'name' => 'Laptops',
            'description' => 'Laptop computers',
            'parent_id' => $electronicsId,
        ]);
        $laptopsId = $laptopsResponse->json('data.id');

        $this->postJson(route('v1.products.store'), [
            'name' => 'iPhone 16',
            'price' => 999,
            'quantity' => 30,
            'category_id' => $phonesId,
        ])->assertStatus(Response::HTTP_CREATED);

        $this->postJson(route('v1.products.store'), [
            'name' => 'MacBook Pro',
            'price' => 2499,
            'quantity' => 15,
            'category_id' => $laptopsId,
        ])->assertStatus(Response::HTTP_CREATED);

        $this->getJson(route('v1.categories.subcategories', $electronicsId))
            ->assertStatus(Response::HTTP_OK);

        $this->getJson(route('v1.products.index'))
            ->assertStatus(Response::HTTP_OK)
            ->assertJsonFragment(['name' => 'iPhone 16'])
            ->assertJsonFragment(['name' => 'MacBook Pro']);
    }
}
