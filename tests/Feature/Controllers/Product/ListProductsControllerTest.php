<?php

namespace Tests\Feature\Controllers\Product;

use App\Models\Category\CategoryModel;
use App\Models\Product\ProductModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class ListProductsControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_returns_list_of_products(): void
    {
        ProductModel::factory()->count(3)->create();

        $response = $this->getJson(route('v1.products.index'));

        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id',
                        'name',
                    ],
                ],
                'meta' => [
                    'current_page',
                    'per_page',
                    'total',
                    'last_page',
                ],
            ]);
    }

    public function test_list_products_with_pagination_returns_correct_page(): void
    {
        ProductModel::factory()->count(30)->create();

        $page = 2;
        $perPage = 10;

        $this->getJson(route('v1.products.index', ['page' => $page, 'per_page' => $perPage]));

        $response = $this->getJson(route('v1.products.index', [
            'page' => $page,
            'per_page' => $perPage,
        ]));

        $response->assertStatus(Response::HTTP_OK);

        $response->assertJsonStructure([
            'success',
            'data' => [
                '*' => [
                    'id',
                    'name',
                    'description',
                    'price',
                ],
            ],
            'meta' => [
                'current_page',
                'per_page',
                'total',
                'last_page',
            ],
        ]);

        $json = $response->json();
        $this->assertEquals($page, $json['meta']['current_page']);
        $this->assertEquals($perPage, $json['meta']['per_page']);
        $this->assertEquals(30, $json['meta']['total']);
        $this->assertEquals(3, $json['meta']['last_page']);

        $this->assertCount($perPage, $json['data']);

        $expectedIds = range(11, 20);
        $actualIds = array_column($json['data'], 'id');
        $this->assertEquals($expectedIds, $actualIds);
    }

    public function test_list_products_with_sorting_by_price(): void
    {
        ProductModel::factory()->create(['price' => 100.00]);
        ProductModel::factory()->create(['price' => 50.00]);
        ProductModel::factory()->create(['price' => 75.00]);

        $response = $this->getJson(route('v1.products.index', [
            'sort' => 'price',
            'order' => 'asc',
        ]));

        $response->assertStatus(Response::HTTP_OK);
        $json = $response->json();

        $prices = array_column($json['data'], 'price');
        $this->assertEquals([50, 75, 100], $prices);
    }

    public function test_filter_by_search_matches_name(): void
    {
        ProductModel::factory()->create(['name' => 'Gaming Laptop', 'description' => 'A fast machine']);
        ProductModel::factory()->create(['name' => 'Office Chair', 'description' => 'Ergonomic design']);

        $response = $this->getJson(route('v1.products.index', [
            'filter' => ['search' => 'laptop'],
        ]));

        $response->assertStatus(Response::HTTP_OK);
        $json = $response->json();

        $this->assertCount(1, $json['data']);
        $this->assertEquals('Gaming Laptop', $json['data'][0]['name']);
    }

    public function test_filter_by_search_matches_description(): void
    {
        ProductModel::factory()->create(['name' => 'Widget', 'description' => 'A portable laptop stand']);
        ProductModel::factory()->create(['name' => 'Gadget', 'description' => 'Kitchen appliance']);

        $response = $this->getJson(route('v1.products.index', [
            'filter' => ['search' => 'laptop'],
        ]));

        $response->assertStatus(Response::HTTP_OK);
        $json = $response->json();

        $this->assertCount(1, $json['data']);
        $this->assertEquals('Widget', $json['data'][0]['name']);
    }

    public function test_filter_by_search_matches_name_or_description(): void
    {
        ProductModel::factory()->create(['name' => 'Laptop Pro', 'description' => 'High performance']);
        ProductModel::factory()->create(['name' => 'Stand', 'description' => 'Holds your laptop']);
        ProductModel::factory()->create(['name' => 'Mouse', 'description' => 'Wireless mouse']);

        $response = $this->getJson(route('v1.products.index', [
            'filter' => ['search' => 'laptop'],
        ]));

        $response->assertStatus(Response::HTTP_OK);
        $json = $response->json();

        $this->assertCount(2, $json['data']);
        $names = array_column($json['data'], 'name');
        $this->assertContains('Laptop Pro', $names);
        $this->assertContains('Stand', $names);
    }

    public function test_filter_by_category_ids_returns_products_from_multiple_categories(): void
    {
        $electronics = CategoryModel::factory()->create(['name' => 'Electronics']);
        $clothing = CategoryModel::factory()->create(['name' => 'Clothing']);
        $books = CategoryModel::factory()->create(['name' => 'Books']);

        ProductModel::factory()->create(['name' => 'Laptop', 'category_id' => $electronics->id]);
        ProductModel::factory()->create(['name' => 'T-Shirt', 'category_id' => $clothing->id]);
        ProductModel::factory()->create(['name' => 'Novel', 'category_id' => $books->id]);

        $response = $this->getJson(route('v1.products.index', [
            'filter' => ['category_ids' => $electronics->id . ',' . $clothing->id],
        ]));

        $response->assertStatus(Response::HTTP_OK);
        $json = $response->json();

        $this->assertCount(2, $json['data']);
        $names = array_column($json['data'], 'name');
        $this->assertContains('Laptop', $names);
        $this->assertContains('T-Shirt', $names);
        $this->assertNotContains('Novel', $names);
    }

    public function test_filter_by_category_ids_with_single_id(): void
    {
        $cat = CategoryModel::factory()->create();
        ProductModel::factory()->create(['category_id' => $cat->id]);
        ProductModel::factory()->create();

        $response = $this->getJson(route('v1.products.index', [
            'filter' => ['category_ids' => (string) $cat->id],
        ]));

        $response->assertStatus(Response::HTTP_OK);
        $json = $response->json();

        $this->assertCount(1, $json['data']);
    }

    public function test_filter_by_category_ids_validation_rejects_non_numeric(): void
    {
        $response = $this->getJson(route('v1.products.index', [
            'filter' => ['category_ids' => 'abc,def'],
        ]));

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function test_search_combined_with_other_filters(): void
    {
        $cat = CategoryModel::factory()->create();

        ProductModel::factory()->create([
            'name' => 'Cheap Laptop',
            'description' => 'Budget option',
            'price' => 200,
            'category_id' => $cat->id,
        ]);
        ProductModel::factory()->create([
            'name' => 'Expensive Laptop',
            'description' => 'Premium option',
            'price' => 2000,
            'category_id' => $cat->id,
        ]);
        ProductModel::factory()->create([
            'name' => 'Cheap Mouse',
            'description' => 'Basic mouse',
            'price' => 10,
            'category_id' => $cat->id,
        ]);

        $response = $this->getJson(route('v1.products.index', [
            'filter' => [
                'search' => 'laptop',
                'min_price' => 0,
                'max_price' => 500,
            ],
        ]));

        $response->assertStatus(Response::HTTP_OK);
        $json = $response->json();

        $this->assertCount(1, $json['data']);
        $this->assertEquals('Cheap Laptop', $json['data'][0]['name']);
    }
}
