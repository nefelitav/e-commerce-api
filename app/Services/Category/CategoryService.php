<?php

namespace App\Services\Category;

use App\Dto\Category\Category;
use App\Dto\Category\UnpersistedCategory;
use App\Exceptions\CategoryAlreadyExistsException;
use App\Exceptions\CategoryNotFoundException;
use App\Repositories\Category\CategoryRepositoryInterface;
use App\Services\AuditLogger;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;

final readonly class CategoryService implements CategoryServiceInterface
{
    public function __construct(
        private CategoryRepositoryInterface $repository,
        private AuditLogger $auditLogger,
    ) {
    }

    /**
     * @param array<string, mixed> $filters
     * @param array<string> $includes
     * @return LengthAwarePaginator<int, Category>
     */
    public function listCategories(
        int $page = 1,
        int $perPage = 15,
        string $sort = 'id',
        string $order = 'asc',
        array $filters = [],
        array $includes = []
    ): LengthAwarePaginator {
        return $this->repository->getAll($page, $perPage, $sort, $order, $filters, $includes);
    }

    /**
     * @return array<Category>
     */
    public function listSubcategories(int $id): array
    {
        return $this->repository->getSubcategoriesById($id);
    }

    public function getCategoryById(int $id): ?Category
    {
        return $this->repository->findById($id);
    }

    /**
     * @throws CategoryAlreadyExistsException
     */
    public function createCategory(UnpersistedCategory $unpersistedCategory): Category
    {
        $existing = $this->repository->findByName($unpersistedCategory->name);

        if ($existing) {
            throw new CategoryAlreadyExistsException($unpersistedCategory->name);
        }

        $category = $this->repository->persist($unpersistedCategory);

        Cache::tags(['categories'])->flush();

        $this->auditLogger->log('category.created', 'category', $category->id, [
            'name' => $category->name,
            'parent_id' => $category->parentId,
        ]);

        return $category;
    }

    /**
     * @throws CategoryNotFoundException
     * @throws CategoryAlreadyExistsException
     */
    public function updateCategory(int $id, UnpersistedCategory $unpersistedCategory): Category
    {
        $existing = $this->repository->findByName($unpersistedCategory->name);

        if ($existing && $existing->id !== $id) {
            throw new CategoryAlreadyExistsException($unpersistedCategory->name);
        }

        $category = $this->repository->update($id, $unpersistedCategory);

        Cache::tags(['categories'])->flush();

        $this->auditLogger->log('category.updated', 'category', $id, [
            'name' => $category->name,
            'parent_id' => $category->parentId,
        ]);

        return $category;
    }

    /**
     * @throws CategoryNotFoundException
     */
    public function deleteCategory(int $id): bool
    {
        $result = $this->repository->delete($id);

        Cache::tags(['categories'])->flush();

        $this->auditLogger->log('category.deleted', 'category', $id);

        return $result;
    }
}
