<?php

namespace Tests\Unit\Services;

use App\Dto\Category\Category;
use App\Dto\Category\UnpersistedCategory;
use App\Exceptions\CategoryAlreadyExistsException;
use App\Exceptions\CategoryNotFoundException;
use App\Models\Category\CategoryModel;
use App\Repositories\Category\CategoryRepositoryInterface;
use App\Services\AuditLogger;
use App\Services\Category\CategoryService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Tests\TestCase;

class CategoryServiceTest extends TestCase
{
    use RefreshDatabase;

    /** @var CategoryRepositoryInterface&\PHPUnit\Framework\MockObject\MockObject */
    private CategoryRepositoryInterface $repository;

    private CategoryService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = $this->createMock(CategoryRepositoryInterface::class);
        $this->service = new CategoryService($this->repository, new AuditLogger);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }

    public function test_list_categories_returns_array_of_categories(): void
    {
        $categoryModels = CategoryModel::factory()->count(2)->make();

        $paginator = new LengthAwarePaginator(
            $categoryModels,
            count($categoryModels),
            15,
            1,
        );

        $this->repository
            ->expects($this->once())
            ->method('getAll')
            ->willReturn($paginator);

        $result = $this->service->listCategories();

        $this->assertSame($paginator, $result);
    }

    public function test_list_subcategories_returns_array_of_categories(): void
    {
        $parent_id = 1;
        $subcategories = [CategoryModel::factory()->create()];
        $this->repository
            ->expects($this->once())
            ->method('getSubcategoriesById')
            ->with($parent_id)
            ->willReturn($subcategories);

        $result = $this->service->listSubcategories($parent_id);

        $this->assertSame($subcategories, $result);
    }

    public function test_get_category_by_id_returns_category(): void
    {
        $id = 1;
        $category = Category::fromModel(CategoryModel::factory()->create());

        $this->repository
            ->expects($this->once())
            ->method('findById')
            ->with($id)
            ->willReturn($category);

        $result = $this->service->getCategoryById($id);

        $this->assertSame($category, $result);
    }

    public function test_get_category_by_id_returns_null(): void
    {
        $id = 1;

        $this->repository
            ->expects($this->once())
            ->method('findById')
            ->with($id)
            ->willReturn(null);

        $result = $this->service->getCategoryById($id);

        $this->assertNull($result);
    }

    public function test_create_category_maps_unique_violation_to_already_exists(): void
    {
        $unpersisted = new UnpersistedCategory(
            name: 'ExistingCategory',
            description: 'Some description',
            parentId: null,
        );

        $this->repository
            ->expects($this->once())
            ->method('persist')
            ->willThrowException(new UniqueConstraintViolationException('pgsql', 'insert', [], new \Exception('duplicate key')));

        $this->expectException(CategoryAlreadyExistsException::class);

        $this->service->createCategory($unpersisted);
    }

    public function test_create_category_persists_and_returns_category(): void
    {
        $unpersisted = new UnpersistedCategory(
            name: 'NewCategory',
            description: 'Some description',
            parentId: null,
        );

        $persistedCategory = Category::fromModel(CategoryModel::factory()->create());

        $this->repository
            ->expects($this->once())
            ->method('persist')
            ->with($unpersisted)
            ->willReturn($persistedCategory);

        $result = $this->service->createCategory($unpersisted);

        $this->assertEquals($persistedCategory, $result);
    }

    public function test_update_category_calls_repository_update(): void
    {
        $id = 1;
        $unpersisted = new UnpersistedCategory(
            name: 'UpdatedCategory',
            description: 'Updated description',
            parentId: null,
        );
        $updatedCategory = Category::fromModel(CategoryModel::factory()->create());

        $this->repository
            ->expects($this->once())
            ->method('update')
            ->with($id, $unpersisted)
            ->willReturn($updatedCategory);

        $result = $this->service->updateCategory($id, $unpersisted);

        $this->assertSame($updatedCategory, $result);
    }

    public function test_update_category_throws_category_not_found_exception(): void
    {
        $id = 1;

        $unpersisted = new UnpersistedCategory(
            name: 'UpdatedCategory',
            description: 'Updated description',
            parentId: null,
        );

        $this->repository
            ->expects($this->once())
            ->method('update')
            ->with($id, $unpersisted)
            ->willThrowException(new CategoryNotFoundException($id));

        $this->expectException(CategoryNotFoundException::class);

        $this->service->updateCategory($id, $unpersisted);
    }

    public function test_delete_category_returns_true(): void
    {
        $id = 1;

        $this->repository
            ->expects($this->once())
            ->method('delete')
            ->with($id)
            ->willReturn(true);

        $result = $this->service->deleteCategory($id);

        $this->assertTrue($result);
    }

    public function test_delete_category_throws_category_not_found_exception(): void
    {
        $id = 1;

        $this->repository
            ->expects($this->once())
            ->method('delete')
            ->with($id)
            ->willThrowException(new CategoryNotFoundException($id));

        $this->expectException(CategoryNotFoundException::class);

        $this->service->deleteCategory($id);
    }
}
