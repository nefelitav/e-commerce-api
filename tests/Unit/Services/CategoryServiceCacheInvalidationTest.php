<?php

namespace Tests\Unit\Services;

use App\Dto\Category\Category;
use App\Dto\Category\UnpersistedCategory;
use App\Exceptions\CategoryNotFoundException;
use App\Models\Category\CategoryModel;
use App\Repositories\Category\CategoryRepository;
use App\Services\AuditLogger;
use App\Services\Category\CategoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class CategoryServiceCacheInvalidationTest extends TestCase
{
    use RefreshDatabase;

    /** @var CategoryRepository&\PHPUnit\Framework\MockObject\MockObject */
    private CategoryRepository $repository;
    private CategoryService $service;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::tags(['categories'])->flush();
        $this->repository = $this->createMock(CategoryRepository::class);
        $this->service = new CategoryService($this->repository, new AuditLogger());
    }

    protected function tearDown(): void
    {
        Cache::tags(['categories'])->flush();
        parent::tearDown();
    }

    public function test_createCategory_flushes_category_cache(): void
    {
        Cache::tags(['categories'])->put('categories.all.test', 'cached_value', 1800);
        Cache::tags(['categories'])->put('categories.1', 'cached_value', 1800);

        $this->assertTrue(Cache::tags(['categories'])->has('categories.all.test'));

        $unpersisted = new UnpersistedCategory(
            name: 'NewCategory',
            description: 'Some description',
            parentId: null,
        );

        $persistedCategory = Category::fromModel(CategoryModel::factory()->create());

        $this->repository->method('findByName')->willReturn(null);
        $this->repository->method('persist')->willReturn($persistedCategory);

        $this->service->createCategory($unpersisted);

        $this->assertFalse(Cache::tags(['categories'])->has('categories.all.test'));
        $this->assertFalse(Cache::tags(['categories'])->has('categories.1'));
    }

    public function test_updateCategory_flushes_category_cache(): void
    {
        Cache::tags(['categories'])->put('categories.5', 'cached_value', 1800);
        Cache::tags(['categories'])->put('categories.all.test', 'cached_value', 1800);

        $unpersisted = new UnpersistedCategory(
            name: 'UpdatedCategory',
            description: 'Updated description',
            parentId: null,
        );

        $updatedCategory = Category::fromModel(CategoryModel::factory()->create());

        $this->repository->method('findByName')->willReturn(null);
        $this->repository->method('update')->willReturn($updatedCategory);

        $this->service->updateCategory(5, $unpersisted);

        $this->assertFalse(Cache::tags(['categories'])->has('categories.5'));
        $this->assertFalse(Cache::tags(['categories'])->has('categories.all.test'));
    }

    public function test_deleteCategory_flushes_category_cache(): void
    {
        Cache::tags(['categories'])->put('categories.3', 'cached_value', 1800);
        Cache::tags(['categories'])->put('categories.3.children', 'cached_value', 1800);

        $this->repository->method('delete')->willReturn(true);

        $this->service->deleteCategory(3);

        $this->assertFalse(Cache::tags(['categories'])->has('categories.3'));
        $this->assertFalse(Cache::tags(['categories'])->has('categories.3.children'));
    }

    public function test_cache_not_flushed_when_createCategory_throws(): void
    {
        Cache::tags(['categories'])->put('categories.all.test', 'cached_value', 1800);

        $unpersisted = new UnpersistedCategory(
            name: 'ExistingCategory',
            description: 'desc',
            parentId: null,
        );

        $existingCategory = Category::fromModel(CategoryModel::factory()->create());
        $this->repository->method('findByName')->willReturn($existingCategory);

        try {
            $this->service->createCategory($unpersisted);
        } catch (\Throwable) {
        }

        $this->assertTrue(Cache::tags(['categories'])->has('categories.all.test'));
    }

    public function test_cache_not_flushed_when_deleteCategory_throws(): void
    {
        Cache::tags(['categories'])->put('categories.all.test', 'cached_value', 1800);

        $this->repository->method('delete')->willThrowException(new CategoryNotFoundException(999));

        try {
            $this->service->deleteCategory(999);
        } catch (\Throwable) {
        }

        $this->assertTrue(Cache::tags(['categories'])->has('categories.all.test'));
    }
}

