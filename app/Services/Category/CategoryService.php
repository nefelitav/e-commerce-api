<?php

namespace App\Services\Category;

use App\Dto\Category\Category;
use App\Dto\Category\UnpersistedCategory;
use App\Exceptions\CategoryAlreadyExistsException;
use App\Exceptions\CategoryNotFoundException;
use App\Repositories\Category\CategoryRepository;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;

final readonly class CategoryService
{
    public function __construct(
        private CategoryRepository $repository,
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

        return $category;
    }

    /**
     * @throws CategoryNotFoundException
     * @throws CategoryAlreadyExistsException
     */
    public function updateCategory(int $id, UnpersistedCategory $unpersistedCategory): Category
    {
        $existing = $this->repository->findByName($unpersistedCategory->name);

        if ($existing) {
            throw new CategoryAlreadyExistsException($unpersistedCategory->name);
        }

        $category = $this->repository->update($id, $unpersistedCategory);

        Cache::tags(['categories'])->flush();

        return $category;
    }

    /**
     * @throws CategoryNotFoundException
     */
    public function deleteCategory(int $id): bool
    {
        $result = $this->repository->delete($id);

        Cache::tags(['categories'])->flush();

        return $result;
    }
}
