<?php

namespace App\Services\Category;

use App\Dto\Category\Category;
use App\Dto\Category\UnpersistedCategory;
use App\Exceptions\CategoryAlreadyExistsException;
use App\Exceptions\CategoryNotFoundException;
use App\Repositories\Category\CategoryRepository;

final readonly class CategoryService
{
    public function __construct(
        private CategoryRepository $repository,
    ) {
    }

    /**
     * @return array<Category>
     */
    public function listCategories(): array
    {
        return $this->repository->getAll();
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

        return $this->repository->persist($unpersistedCategory);
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

        return $this->repository->update($id, $unpersistedCategory);
    }

    /**
     * @throws CategoryNotFoundException
     */
    public function deleteCategory(int $id): bool
    {
        return $this->repository->delete($id);
    }
}
