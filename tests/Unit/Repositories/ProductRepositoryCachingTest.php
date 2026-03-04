<?php

namespace Tests\Unit\Repositories;

use App\Dto\Product\UnpersistedProduct;
use App\Models\Category\CategoryModel;
use App\Models\Product\ProductModel;
use App\Repositories\Product\ProductRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProductRepositoryCachingTest extends TestCase
{
    use RefreshDatabase;

    private ProductRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::tags(['products'])->flush();
        $this->repository = new ProductRepository();
    }

    protected function tearDown(): void
    {
        Cache::tags(['products'])->flush();
        parent::tearDown();
    }

    public function test_findById_caches_result_on_first_call(): void
    {
        $product = ProductModel::factory()->create();
        $cacheKey = "products.{$product->id}";

        $this->assertFalse(Cache::tags(['products'])->has($cacheKey));

        $result = $this->repository->findById($product->id);

        $this->assertTrue(Cache::tags(['products'])->has($cacheKey));
        $this->assertNotNull($result);
        $this->assertEquals($product->name, $result->name);
    }

    public function test_findById_returns_cached_result_on_second_call(): void
    {
        $product = ProductModel::factory()->create();

        $firstResult = $this->repository->findById($product->id);
        $secondResult = $this->repository->findById($product->id);

        $this->assertEquals($firstResult, $secondResult);
    }

    public function test_findById_returns_null_for_missing_product(): void
    {
        $result = $this->repository->findById(999);

        $this->assertNull($result);
    }

    public function test_getAll_caches_result(): void
    {
        ProductModel::factory()->count(3)->create();

        $cacheKey = 'products.all.' . md5(serialize([1, 15, 'id', 'asc', [], []]));

        $this->assertFalse(Cache::tags(['products'])->has($cacheKey));

        $this->repository->getAll();

        $this->assertTrue(Cache::tags(['products'])->has($cacheKey));
    }

    public function test_getAll_returns_same_result_from_cache(): void
    {
        ProductModel::factory()->count(2)->create();

        $firstResult = $this->repository->getAll();
        $secondResult = $this->repository->getAll();

        $this->assertEquals($firstResult->total(), $secondResult->total());
        $this->assertCount(2, $firstResult);
    }

    public function test_getAll_caches_different_keys_for_different_params(): void
    {
        ProductModel::factory()->count(3)->create();

        $key1 = 'products.all.' . md5(serialize([1, 15, 'id', 'asc', [], []]));
        $key2 = 'products.all.' . md5(serialize([1, 15, 'name', 'desc', [], []]));

        $this->repository->getAll(sort: 'id', order: 'asc');
        $this->repository->getAll(sort: 'name', order: 'desc');

        $this->assertTrue(Cache::tags(['products'])->has($key1));
        $this->assertTrue(Cache::tags(['products'])->has($key2));
    }

    public function test_findByCategoryId_caches_result(): void
    {
        $category = CategoryModel::factory()->create();
        ProductModel::factory()->count(2)->create(['category_id' => $category->id]);

        $cacheKey = "products.category.{$category->id}";

        $this->assertFalse(Cache::tags(['products'])->has($cacheKey));

        $result = $this->repository->findByCategoryId($category->id);

        $this->assertTrue(Cache::tags(['products'])->has($cacheKey));
        $this->assertCount(2, $result);
    }

    public function test_findByCategoryId_caches_empty_array_for_unknown_category(): void
    {
        $result = $this->repository->findByCategoryId(999);

        $this->assertSame([], $result);
        $this->assertTrue(Cache::tags(['products'])->has('products.category.999'));
    }

    public function test_findByIdForUpdate_is_not_cached(): void
    {
        $product = ProductModel::factory()->create();

        DB::transaction(function () use ($product) {
            $this->repository->findByIdForUpdate($product->id);
        });

        $this->assertFalse(Cache::tags(['products'])->has("products.{$product->id}"));
    }

    public function test_findByName_is_not_cached(): void
    {
        $product = ProductModel::factory()->create(['name' => 'Unique Widget']);

        $result = $this->repository->findByName('Unique Widget');

        $this->assertNotNull($result);
        $this->assertFalse(Cache::tags(['products'])->has('products.name.' . md5('Unique Widget')));
    }

    public function test_persist_does_not_cache_result(): void
    {
        $category = CategoryModel::factory()->create();

        $dto = new UnpersistedProduct(
            name: 'New Product',
            description: 'Description',
            price: 99.99,
            quantity: 10,
            categoryId: $category->id,
        );

        $result = $this->repository->persist($dto);

        $this->assertFalse(Cache::tags(['products'])->has("products.{$result->id}"));
    }
}


