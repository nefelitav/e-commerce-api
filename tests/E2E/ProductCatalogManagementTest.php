<?php

namespace Tests\E2E;

use App\Enums\InventoryChangeType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpFoundation\Response;
use Tests\Fixtures\CatalogFixture;
use Tests\Fixtures\OrderFixture;
use Tests\Fixtures\UserFixture;
use Tests\TestCase;
use Tests\Traits\InteractsWithShopApi;

class ProductCatalogManagementTest extends TestCase
{
    use InteractsWithShopApi;
    use RefreshDatabase;

    public function test_full_catalog_setup_with_nested_categories_and_products(): void
    {
        $admin = UserFixture::admin();
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

        ['productId' => $shirtId] = $this->createProductViaApi('Cotton T-Shirt', 29.99, 100, $menCategoryId, $admin);

        $this->createProductViaApi('Summer Dress', 59.99, 50, $womenCategoryId, $admin);

        $guest = UserFixture::customer();
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
        ['admin' => $admin, 'customer' => $customer] = UserFixture::adminAndCustomer();

        $this->actingAs($admin);
        $category = CatalogFixture::category(['name' => 'Gadgets']);

        ['productId' => $productId] = $this->createProductViaApi('Portable Charger', 39.99, 20, $category->id, $admin);

        $this->actingAs($customer);
        $realProduct = \App\Models\Product\ProductModel::findOrFail($productId);
        $this->postJson(route('v1.orders.store'), OrderFixture::payload($realProduct, 2))
            ->assertStatus(Response::HTTP_CREATED);

        $this->assertDatabaseHas('products', ['id' => $productId, 'quantity' => 18]);

        // Verify inventory history was tracked
        $this->actingAs($admin);
        $this->getJson(route('v1.products.inventory-history.index', $productId))
            ->assertStatus(Response::HTTP_OK);

        $this->assertDatabaseHas('inventory_history', [
            'product_id' => $productId,
            'change_type' => InventoryChangeType::Sale->value,
            'quantity_changed' => -2,
        ]);
    }

    public function test_category_hierarchy_browsing(): void
    {
        $admin = UserFixture::admin();
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

        $this->createProductViaApi('iPhone 16', 999, 30, $phonesId, $admin);
        $this->createProductViaApi('MacBook Pro', 2499, 15, $laptopsId, $admin);

        $this->getJson(route('v1.categories.subcategories', $electronicsId))
            ->assertStatus(Response::HTTP_OK);

        $this->getJson(route('v1.products.index'))
            ->assertStatus(Response::HTTP_OK)
            ->assertJsonFragment(['name' => 'iPhone 16'])
            ->assertJsonFragment(['name' => 'MacBook Pro']);
    }
}
