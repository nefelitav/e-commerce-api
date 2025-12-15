<?php

namespace Tests\Unit\Repositories;

use App\Dto\Category\UnpersistedCategory;
use App\Exceptions\CategoryNotFoundException;
use App\Models\Category\CategoryModel;
use App\Repositories\Category\CategoryRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private CategoryRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = new CategoryRepository();
    }

    public function test_it_returns_all_categories(): void
    {
        CategoryModel::factory()->count(3)->create();

        $categories = $this->repository->getAll();

        $this->assertCount(3, $categories);
        $this->assertEquals(
            CategoryModel::query()->first()->name,
            $categories[0]->name
        );
    }

    public function test_it_returns_subcategories_by_parent_id(): void
    {
        $parent = CategoryModel::factory()->create();
        $children = CategoryModel::factory()
            ->count(2)
            ->create(['parent_id' => $parent->id]);

        $result = $this->repository->getSubcategoriesById($parent->id);

        $this->assertCount(2, $result);
        $this->assertEquals($children[0]->name, $result[0]->name);
    }

    public function test_it_returns_empty_array_if_parent_category_not_found(): void
    {
        $result = $this->repository->getSubcategoriesById(999);

        $this->assertSame([], $result);
    }

    public function test_it_finds_category_by_id(): void
    {
        $category = CategoryModel::factory()->create();

        $result = $this->repository->findById($category->id);

        $this->assertNotNull($result);
        $this->assertEquals($category->name, $result->name);
    }

    public function test_it_returns_null_when_category_by_id_not_found(): void
    {
        $result = $this->repository->findById(999);

        $this->assertNull($result);
    }

    public function test_it_finds_category_by_name(): void
    {
        CategoryModel::factory()->create([
            'name' => 'Electronics',
        ]);

        $result = $this->repository->findByName('Electronics');

        $this->assertNotNull($result);
        $this->assertEquals('Electronics', $result->name);
    }

    public function test_it_persists_a_new_category(): void
    {
        $dto = new UnpersistedCategory(
            name: 'Books',
            description: 'Description',
            parentId: null,
        );

        $result = $this->repository->persist($dto);

        $this->assertDatabaseHas('categories', [
            'name' => 'Books',
        ]);

        $this->assertEquals('Books', $result->name);
    }

    public function test_it_updates_an_existing_category(): void
    {
        $category = CategoryModel::factory()->create([
            'name' => 'Old Name',
        ]);

        $dto = new UnpersistedCategory(
            name: 'New Name',
            description: 'Description',
            parentId: null,
        );

        $result = $this->repository->update($category->id, $dto);

        $this->assertEquals('New Name', $result->name);
        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
            'name' => 'New Name',
        ]);
    }

    public function test_it_throws_exception_when_updating_non_existing_category(): void
    {
        $this->expectException(CategoryNotFoundException::class);

        $dto = new UnpersistedCategory(
            name: 'Does Not Exist',
            description: 'Description',
            parentId: null,
        );

        $this->repository->update(999, $dto);
    }

    public function test_it_deletes_an_existing_category(): void
    {
        $category = CategoryModel::factory()->create();

        $result = $this->repository->delete($category->id);

        $this->assertTrue($result);
        $this->assertDatabaseMissing('categories', [
            'id' => $category->id,
        ]);
    }

    public function test_it_throws_exception_when_deleting_non_existing_category(): void
    {
        $this->expectException(CategoryNotFoundException::class);

        $this->repository->delete(999);
    }
}
