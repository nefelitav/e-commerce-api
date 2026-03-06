<?php

namespace Tests\Performance;

use App\Models\Category\CategoryModel;
use App\Models\Order\OrderModel;
use App\Models\Product\ProductModel;
use App\Models\UserModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class ThroughputTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_listing_handles_large_dataset(): void
    {
        $category = CategoryModel::factory()->create();
        ProductModel::factory()->count(200)->create(['category_id' => $category->id]);

        $start = microtime(true);

        $response = $this->getJson(route('v1.products.index', ['per_page' => 50]));

        $elapsed = (microtime(true) - $start) * 1000;

        $response->assertStatus(Response::HTTP_OK);
        $this->assertLessThan(
            1000,
            $elapsed,
            "Listing products from a 200-record dataset took {$elapsed}ms",
        );
    }

    public function test_paginated_products_performance_consistency(): void
    {
        $category = CategoryModel::factory()->create();
        ProductModel::factory()->count(100)->create(['category_id' => $category->id]);

        $timings = [];

        for ($page = 1; $page <= 5; $page++) {
            $start = microtime(true);

            $response = $this->getJson(route('v1.products.index', [
                'page' => $page,
                'per_page' => 20,
            ]));

            $timings[] = (microtime(true) - $start) * 1000;

            $response->assertStatus(Response::HTTP_OK);
        }

        $maxTiming = max($timings);
        $this->assertLessThan(
            1000,
            $maxTiming,
            "Slowest page took {$maxTiming}ms across 5 pages of 20 items",
        );
    }

    public function test_concurrent_order_creation_performance(): void
    {
        $user = UserModel::factory()->create();
        $this->actingAs($user);

        $products = ProductModel::factory()->count(5)->create(['quantity' => 1000]);

        $timings = [];

        for ($i = 0; $i < 10; $i++) {
            $product = $products[$i % 5];

            $start = microtime(true);

            $response = $this->postJson(route('v1.orders.store'), [
                'status' => 'pending',
                'total_price' => $product->price * 2,
                'items' => [
                    ['product_id' => $product->id, 'quantity' => 2, 'unit_price' => $product->price],
                ],
            ]);

            $timings[] = (microtime(true) - $start) * 1000;

            $response->assertStatus(Response::HTTP_CREATED);
        }

        $avgTiming = array_sum($timings) / count($timings);
        $this->assertLessThan(
            1000,
            $avgTiming,
            "Average order creation time was {$avgTiming}ms across 10 orders",
        );
    }

    public function test_category_listing_with_many_subcategories(): void
    {
        $parent = CategoryModel::factory()->create();
        CategoryModel::factory()->count(50)->create(['parent_id' => $parent->id]);

        $start = microtime(true);

        $response = $this->getJson(route('v1.categories.subcategories', $parent->id));

        $elapsed = (microtime(true) - $start) * 1000;

        $response->assertStatus(Response::HTTP_OK);
        $this->assertLessThan(
            500,
            $elapsed,
            "Listing 50 subcategories took {$elapsed}ms",
        );
    }

    public function test_orders_listing_with_large_history(): void
    {
        $user = UserModel::factory()->create();
        OrderModel::factory()->count(100)->create(['user_id' => $user->id]);

        $this->actingAs($user);

        $start = microtime(true);

        $response = $this->getJson(route('v1.orders.index', ['per_page' => 25]));

        $elapsed = (microtime(true) - $start) * 1000;

        $response->assertStatus(Response::HTTP_OK);
        $this->assertLessThan(
            1000,
            $elapsed,
            "Listing orders from 100-record history took {$elapsed}ms",
        );
    }

    public function test_product_creation_batch_performance(): void
    {
        $admin = UserModel::factory()->admin()->create();
        $this->actingAs($admin);

        $category = CategoryModel::factory()->create();

        $timings = [];

        for ($i = 0; $i < 20; $i++) {
            $start = microtime(true);

            $response = $this->postJson(route('v1.products.store'), [
                'name' => "Batch Product {$i}",
                'description' => "Batch test product number {$i}",
                'price' => 10.00 + $i,
                'quantity' => 100,
                'category_id' => $category->id,
            ]);

            $timings[] = (microtime(true) - $start) * 1000;

            $response->assertStatus(Response::HTTP_CREATED);
        }

        $avgTiming = array_sum($timings) / count($timings);
        $this->assertLessThan(
            500,
            $avgTiming,
            "Average product creation time was {$avgTiming}ms across 20 products",
        );
    }
}
