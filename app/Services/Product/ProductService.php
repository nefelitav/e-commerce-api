<?php

namespace App\Services\Product;

use App\Dto\Product\Product;
use App\Dto\Product\UnpersistedProduct;
use App\Exceptions\ProductAlreadyExistsException;
use App\Exceptions\ProductNotFoundException;
use App\Repositories\Product\ProductRepository;

final readonly class ProductService
{
    public function __construct(
        private ProductRepository $repository,
    ) {
    }

    /**
     * @return array<Product>
     */
    public function listProducts(): array
    {
        return $this->repository->getAll();
    }

    /**
     * @return array<Product>|null
     */
    public function getProductsByCategoryId(int $categoryId): ?array
    {
        return $this->repository->findByCategoryId($categoryId);
    }

    public function getProductById(int $id): ?Product
    {
        return $this->repository->findById($id);
    }

    /**
     * @throws ProductAlreadyExistsException
     */
    public function createProduct(UnpersistedProduct $unpersistedProduct): Product
    {
        $existing = $this->repository->findByName($unpersistedProduct->name);

        if ($existing) {
            throw new ProductAlreadyExistsException($unpersistedProduct->name);
        }

        return $this->repository->persist($unpersistedProduct);
    }

    /**
     * @throws ProductNotFoundException
     */
    public function updateProduct(int $id, UnpersistedProduct $unpersistedProduct): Product
    {
        return $this->repository->update($id, $unpersistedProduct);
    }

    /**
     * @throws ProductNotFoundException
     */
    public function deleteProduct(int $id): bool
    {
        return $this->repository->delete($id);
    }
}
