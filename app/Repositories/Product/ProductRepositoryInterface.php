<?php

namespace App\Repositories\Product;

use App\Dto\Product\Product;
use App\Dto\Product\UnpersistedProduct;
use App\Exceptions\ProductNotFoundException;
use App\Models\Product\ProductModel;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Pagination\LengthAwarePaginator;

interface ProductRepositoryInterface
{
    /**
     * @param  array<string, mixed>  $filters
     * @param  array<string>  $includes
     * @return LengthAwarePaginator<int, Product>
     */
    public function getAll(
        int $page = 1,
        int $perPage = 15,
        string $sort = 'id',
        string $order = 'asc',
        array $filters = [],
        array $includes = [],
    ): LengthAwarePaginator;

    /**
     * @return array<Product>|null
     */
    public function findByCategoryId(int $categoryId): ?array;

    public function findById(int $id): ?Product;

    public function findByIdForUpdate(int $id): ?ProductModel;

    /**
     * @throws UniqueConstraintViolationException when the name is already taken
     */
    public function persist(UnpersistedProduct $unpersistedProduct): Product;

    /**
     * @throws ProductNotFoundException
     * @throws UniqueConstraintViolationException when the name is already taken
     */
    public function update(int $id, UnpersistedProduct $unpersistedProduct): Product;

    /**
     * @throws ProductNotFoundException
     */
    public function delete(int $id): bool;
}
