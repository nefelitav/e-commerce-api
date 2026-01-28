<?php

namespace App\Services\Product;

use App\Dto\Product\Product;
use App\Dto\Product\UnpersistedProduct;
use App\Exceptions\ProductAlreadyExistsException;
use App\Exceptions\ProductNotFoundException;
use App\Repositories\InventoryHistory\InventoryHistoryRepository;
use App\Dto\InventoryHistory\UnpersistedInventoryHistoryEntry;
use App\Repositories\Product\ProductRepository;

final readonly class ProductService
{
    public function __construct(
        private ProductRepository $repository,
        private InventoryHistoryRepository $inventoryHistoryRepository,
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

        $created = $this->repository->persist($unpersistedProduct);

        // Record initial stock addition.
        $this->inventoryHistoryRepository->record(new UnpersistedInventoryHistoryEntry(
            productId: $created->id,
            changeType: 'addition',
            quantityChanged: $created->quantity,
            previousQuantity: 0,
            newQuantity: $created->quantity,
        ));

        return $created;
    }

    /**
     * @throws ProductNotFoundException
     */
    public function updateProduct(int $id, UnpersistedProduct $unpersistedProduct): Product
    {
        $existing = $this->repository->findById($id);
        if ($existing === null) {
            throw new ProductNotFoundException($id);
        }

        $updated = $this->repository->update($id, $unpersistedProduct);

        if ($updated->quantity !== $existing->quantity) {
            $this->inventoryHistoryRepository->record(new UnpersistedInventoryHistoryEntry(
                productId: $updated->id,
                changeType: 'adjustment',
                quantityChanged: $updated->quantity - $existing->quantity,
                previousQuantity: $existing->quantity,
                newQuantity: $updated->quantity,
            ));
        }

        return $updated;
    }

    /**
     * @throws ProductNotFoundException
     */
    public function deleteProduct(int $id): bool
    {
        return $this->repository->delete($id);
    }
}
