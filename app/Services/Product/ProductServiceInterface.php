<?php

namespace App\Services\Product;

use App\Dto\Product\Product;
use App\Dto\Product\UnpersistedProduct;
use App\Exceptions\InsufficientStockException;
use App\Exceptions\ProductAlreadyExistsException;
use App\Exceptions\ProductNotFoundException;
use Illuminate\Pagination\LengthAwarePaginator;

interface ProductServiceInterface
{
    /**
     * @param array<string, mixed> $filters
     * @param array<string> $includes
     * @return LengthAwarePaginator<int, Product>
     */
    public function listProducts(
        int $page = 1,
        int $perPage = 15,
        string $sort = 'id',
        string $order = 'asc',
        array $filters = [],
        array $includes = []
    ): LengthAwarePaginator;

    /**
     * @return array<Product>|null
     */
    public function getProductsByCategoryId(int $categoryId): ?array;

    public function getProductById(int $id): ?Product;

    /**
     * @throws ProductAlreadyExistsException
     */
    public function createProduct(UnpersistedProduct $unpersistedProduct): Product;

    /**
     * @throws ProductNotFoundException
     * @throws InsufficientStockException
     */
    public function updateProduct(int $id, UnpersistedProduct $unpersistedProduct): Product;

    /**
     * @throws ProductNotFoundException
     */
    public function deleteProduct(int $id): bool;
}

