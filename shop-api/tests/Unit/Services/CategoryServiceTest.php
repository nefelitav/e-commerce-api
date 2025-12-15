<?php

namespace Tests\Unit\Services;

use App\Dto\Category\Category;
use App\Dto\Category\UnpersistedCategory;
use App\Exceptions\CategoryAlreadyExistsException;
use App\Exceptions\CategoryNotFoundException;
use App\Models\Category\CategoryModel;
use App\Repositories\Category\CategoryRepository;
use App\Services\Category\CategoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class CategoryServiceTest extends TestCase
{
    use RefreshDatabase;

    private CategoryRepository $repository;
    private CategoryService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = Mockery::mock(CategoryRepository::class);
        $this->service = new CategoryService($this->repository);
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_listCategories_returns_array_of_categories(): void
    {
        $categories = [CategoryModel::factory()->create()];
        $this->repository->shouldReceive('getAll')->once()->andReturn($categories);

        $result = $this->service->listCategories();

        $this->assertSame($categories, $result);
    }

    public function test_listSubcategories_returns_array_of_categories(): void
    {
        $parentId = 1;
        $subcategories = [CategoryModel::factory()->create()];
        $this->repository->shouldReceive('getSubcategoriesById')->with($parentId)->once()->andReturn($subcategories);

        $result = $this->service->listSubcategories($parentId);

        $this->assertSame($subcategories, $result);
    }

    public function test_getCategoryById_returns_category_or_null(): void
    {
        $id = 1;
        $category = Category::fromModel(CategoryModel::factory()->create());

        $this->repository->shouldReceive('findById')->with($id)->once()->andReturn($category);
        $result = $this->service->getCategoryById($id);
        $this->assertSame($category, $result);

        $this->repository->shouldReceive('findById')->with($id)->once()->andReturn(null);
        $result = $this->service->getCategoryById($id);
        $this->assertNull($result);
    }

    public function test_createCategory_throws_if_category_exists(): void
    {
        $unpersisted = new UnpersistedCategory(
            name: 'ExistingCategory',
            description: 'Some description',
            parentId: null,
        );

        $existingCategory = CategoryModel::factory()->create();

        $this->repository->shouldReceive('findByName')
            ->with('ExistingCategory')
            ->once()
            ->andReturn(Category::fromModel($existingCategory));

        $this->expectException(CategoryAlreadyExistsException::class);

        $this->service->createCategory($unpersisted);
    }

    public function test_createCategory_persists_and_returns_category(): void
    {
        $unpersisted = new UnpersistedCategory(
            name: 'NewCategory',
            description: 'Some description',
            parentId: null,
        );

        $persistedCategory = Category::fromModel(CategoryModel::factory()->create());

        $this->repository->shouldReceive('findByName')
            ->with('NewCategory')
            ->andReturn(null);
        $this->repository->shouldReceive('persist')
            ->with($unpersisted)
            ->andReturn($persistedCategory);

        $result = $this->service->createCategory($unpersisted);

        $this->assertEquals($persistedCategory, $result);
    }

    public function test_updateCategory_calls_repository_update(): void
    {
        $id = 1;
        $unpersisted = new UnpersistedCategory(
            name: 'UpdatedCategory',
            description: 'Updated description',
            parentId: null,
        );
        $updatedCategory = Category::fromModel(CategoryModel::factory()->create());

        $this->repository->shouldReceive('update')
            ->with($id, $unpersisted)
            ->once()
            ->andReturn($updatedCategory);

        $result = $this->service->updateCategory($id, $unpersisted);

        $this->assertSame($updatedCategory, $result);
    }

    public function test_updateCategory_throws_CategoryNotFoundException(): void
    {
        $id = 1;

        $unpersisted = new UnpersistedCategory(
            name: 'UpdatedCategory',
            description: 'Updated description',
            parentId: null
        );

        $this->repository->shouldReceive('update')
            ->with($id, $unpersisted)
            ->once()
            ->andThrow(new CategoryNotFoundException($id));

        $this->expectException(CategoryNotFoundException::class);

        $this->service->updateCategory($id, $unpersisted);
    }

    public function test_deleteCategory_returns_true(): void
    {
        $id = 1;

        $this->repository->shouldReceive('delete')
            ->with($id)
            ->once()
            ->andReturnTrue();

        $result = $this->service->deleteCategory($id);

        $this->assertTrue($result);
    }

    public function test_deleteCategory_throws_CategoryNotFoundException(): void
    {
        $id = 1;

        $this->repository->shouldReceive('delete')
            ->with($id)
            ->andThrow(new CategoryNotFoundException($id));

        $this->expectException(CategoryNotFoundException::class);

        $this->service->deleteCategory($id);
    }
}
