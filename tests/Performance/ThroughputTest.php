<?php

namespace Tests\Performance;

use App\Models\Order\OrderModel;
use App\Models\Product\ProductModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpFoundation\Response;
use Tests\Fixtures\CatalogFixture;
use Tests\Fixtures\OrderFixture;
use Tests\Fixtures\UserFixture;
use Tests\TestCase;
use Tests\Traits\MeasuresPerformance;

class ThroughputTest extends TestCase
{
    use MeasuresPerformance;
    use RefreshDatabase;

    public function test_product_listing_handles_large_dataset(): void
    {
        CatalogFixture::productsInCategory(200);

        $response = $this->assertGetRespondsWithinMs(
            route('v1.products.index', ['per_page' => 50]),
            1000,
            'Listing products from 200-record dataset',
        );
        $response->assertStatus(Response::HTTP_OK);
    }

    public function test_paginated_products_performance_consistency(): void
    {
        CatalogFixture::productsInCategory(100);

        $this->assertMaxTimeWithinMs(5, 1000, function (int $i) {
            $this->getJson(route('v1.products.index', [
                'page' => $i + 1,
                'per_page' => 20,
            ]))->assertStatus(Response::HTTP_OK);
        }, 'Paginated product listing');
    }

    public function test_concurrent_order_creation_performance(): void
    {
        $user = UserFixture::customer();
        $this->actingAs($user);

        $products = ProductModel::factory()->count(5)->create(['quantity' => 1000]);

        $this->assertAverageTimeWithinMs(10, 1000, function (int $i) use ($products) {
            $product = $products[$i % 5];

            $this->postJson(route('v1.orders.store'), OrderFixture::payload($product, 2))
                ->assertStatus(Response::HTTP_CREATED);
        }, 'Order creation');
    }

    public function test_category_listing_with_many_subcategories(): void
    {
        ['parent' => $parent] = CatalogFixture::categoryHierarchy(50);

        $response = $this->assertGetRespondsWithinMs(
            route('v1.categories.subcategories', $parent->id),
            500,
            'Listing 50 subcategories',
        );
        $response->assertStatus(Response::HTTP_OK);
    }

    public function test_orders_listing_with_large_history(): void
    {
        $user = UserFixture::customer();
        OrderModel::factory()->count(100)->create(['user_id' => $user->id]);
        $this->actingAs($user);

        $response = $this->assertGetRespondsWithinMs(
            route('v1.orders.index', ['per_page' => 25]),
            1000,
            'Listing orders from 100-record history',
        );
        $response->assertStatus(Response::HTTP_OK);
    }

    public function test_product_creation_batch_performance(): void
    {
        $admin = UserFixture::admin();
        $this->actingAs($admin);

        $category = CatalogFixture::category();

        $this->assertAverageTimeWithinMs(20, 500, function (int $i) use ($category) {
            $this->postJson(route('v1.products.store'), [
                'name' => "Batch Product {$i}",
                'description' => "Batch test product number {$i}",
                'price' => 10.00 + $i,
                'quantity' => 100,
                'category_id' => $category->id,
            ])->assertStatus(Response::HTTP_CREATED);
        }, 'Product creation');
    }
}
