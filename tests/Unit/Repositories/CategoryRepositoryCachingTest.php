<?php

namespace Tests\Unit\Repositories;

use App\Dto\Category\Category;
use App\Dto\Category\UnpersistedCategory;
use App\Models\Category\CategoryModel;
use App\Repositories\Category\CategoryRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class CategoryRepositoryCachingTest extends TestCase
{
    use RefreshDatabase;

    private CategoryRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::tags(['categories'])->flush();
        $this->repository = new CategoryRepository();
    }

    protected function tearDown(): void
    {
        Cache::tags(['categories'])->flush();
        parent::tearDown();
    }

    public function test_findById_caches_result_on_first_call(): void
    {
        $category = CategoryModel::factory()->create();
        $cacheKey = "categories.{$category->id}";

        $this->assertFalse(Cache::tags(['categories'])->has($cacheKey));

        $result = $this->repository->findById($category->id);

        $this->assertTrue(Cache::tags(['categories'])->has($cacheKey));
        $this->assertNotNull($result);
        $this->assertEquals($category->name, $result->name);
    }

    public function test_findById_returns_cached_result_on_second_call(): void
    {
        $category = CategoryModel::factory()->create();

        $firstResult = $this->repository->findById($category->id);
        $secondResult = $this->repository->findById($category->id);

        $this->assertEquals($firstResult, $secondResult);
    }

    public function test_findById_returns_null_for_missing_category(): void
    {
        $result = $this->repository->findById(999);

        $this->assertNull($result);
    }

    public function test_findByName_caches_result(): void
    {
        CategoryModel::factory()->create(['name' => 'Electronics']);
        $cacheKey = 'categories.name.' . md5('Electronics');

        $this->assertFalse(Cache::tags(['categories'])->has($cacheKey));

        $result = $this->repository->findByName('Electronics');

        $this->assertTrue(Cache::tags(['categories'])->has($cacheKey));
        $this->assertNotNull($result);
        $this->assertEquals('Electronics', $result->name);
    }

    public function test_getAll_caches_result(): void
    {
        CategoryModel::factory()->count(3)->create();

        $cacheKey = 'categories.all.' . md5(serialize([1, 15, 'id', 'asc', [], []]));

        $this->assertFalse(Cache::tags(['categories'])->has($cacheKey));

        $this->repository->getAll();

        $this->assertTrue(Cache::tags(['categories'])->has($cacheKey));
    }

    public function test_getAll_returns_same_result_from_cache(): void
    {
        CategoryModel::factory()->count(2)->create();

        $firstResult = $this->repository->getAll();
        $secondResult = $this->repository->getAll();

        $this->assertEquals($firstResult->total(), $secondResult->total());
        $this->assertCount(2, $firstResult);
    }

    public function test_getAll_caches_different_keys_for_different_params(): void
    {
        CategoryModel::factory()->count(3)->create();

        $key1 = 'categories.all.' . md5(serialize([1, 15, 'id', 'asc', [], []]));
        $key2 = 'categories.all.' . md5(serialize([1, 15, 'name', 'desc', [], []]));

        $this->repository->getAll(sort: 'id', order: 'asc');
        $this->repository->getAll(sort: 'name', order: 'desc');

        $this->assertTrue(Cache::tags(['categories'])->has($key1));
        $this->assertTrue(Cache::tags(['categories'])->has($key2));
    }

    public function test_getSubcategoriesById_caches_result(): void
    {
        $parent = CategoryModel::factory()->create();
        CategoryModel::factory()->count(2)->create(['parent_id' => $parent->id]);

        $cacheKey = "categories.{$parent->id}.children";

        $this->assertFalse(Cache::tags(['categories'])->has($cacheKey));

        $result = $this->repository->getSubcategoriesById($parent->id);

        $this->assertTrue(Cache::tags(['categories'])->has($cacheKey));
        $this->assertCount(2, $result);
    }

    public function test_getSubcategoriesById_caches_empty_array_for_missing_parent(): void
    {
        $result = $this->repository->getSubcategoriesById(999);

        $this->assertSame([], $result);
        $this->assertTrue(Cache::tags(['categories'])->has('categories.999.children'));
    }

    public function test_persist_does_not_cache_result(): void
    {
        $dto = new UnpersistedCategory(
            name: 'Books',
            description: 'Description',
            parentId: null,
        );

        $result = $this->repository->persist($dto);

        $this->assertFalse(Cache::tags(['categories'])->has("categories.{$result->id}"));
    }
}


