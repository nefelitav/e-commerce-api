<?php

namespace App\Repositories\Category;

use App\Dto\Category\Category;
use App\Dto\Category\UnpersistedCategory;
use App\Exceptions\CategoryNotFoundException;
use Illuminate\Pagination\LengthAwarePaginator;

interface CategoryRepositoryInterface
{
    /**
     * @param array<string, mixed> $filters
     * @param array<string> $includes
     * @return LengthAwarePaginator<int, Category>
     */
    public function getAll(
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
    public function getSubcategoriesById(int $id): array;

    public function findById(int $id): ?Category;

    public function findByName(string $name): ?Category;

    public function persist(UnpersistedCategory $unpersistedCategory): Category;

    /**
     * @throws CategoryNotFoundException
     */
    public function update(int $id, UnpersistedCategory $unpersistedCategory): Category;

    /**
     * @throws CategoryNotFoundException
     */
    public function delete(int $id): bool;
}

