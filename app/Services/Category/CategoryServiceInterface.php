<?php

namespace App\Services\Category;

use App\Dto\Category\Category;
use App\Dto\Category\UnpersistedCategory;
use App\Exceptions\CategoryAlreadyExistsException;
use App\Exceptions\CategoryNotFoundException;
use Illuminate\Pagination\LengthAwarePaginator;

interface CategoryServiceInterface
{
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
    ): LengthAwarePaginator;

    /**
     * @return array<Category>
     */
    public function listSubcategories(int $id): array;

    public function getCategoryById(int $id): ?Category;

    /**
     * @throws CategoryAlreadyExistsException
     */
    public function createCategory(UnpersistedCategory $unpersistedCategory): Category;

    /**
     * @throws CategoryNotFoundException
     * @throws CategoryAlreadyExistsException
     */
    public function updateCategory(int $id, UnpersistedCategory $unpersistedCategory): Category;

    /**
     * @throws CategoryNotFoundException
     */
    public function deleteCategory(int $id): bool;
}

