<?php

namespace App\Services\Product;

use App\Dto\Product\Product;
use App\Dto\Product\UnpersistedProduct;
use App\Exceptions\InsufficientStockException;
use App\Exceptions\ProductAlreadyExistsException;
use App\Exceptions\ProductNotFoundException;
use App\Repositories\InventoryHistory\InventoryHistoryRepository;
use App\Dto\InventoryHistory\UnpersistedInventoryHistoryEntry;
use App\Repositories\Product\ProductRepository;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

final readonly class ProductService implements ProductServiceInterface
{
    public function __construct(
        private ProductRepository $repository,
        private InventoryHistoryRepository $inventoryHistoryRepository,
    ) {
    }

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
    ): LengthAwarePaginator {
        return $this->repository->getAll($page, $perPage, $sort, $order, $filters, $includes);
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

    public function createProduct(UnpersistedProduct $unpersistedProduct): Product
    {
        /** @var Product $created */
        $created = DB::transaction(
            /**
             * @throws ProductAlreadyExistsException
             */
            function () use ($unpersistedProduct) {
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
        );

        return $created;
    }

    public function updateProduct(int $id, UnpersistedProduct $unpersistedProduct): Product
    {
        /** @var Product $updated */
        $updated = DB::transaction(
            /**
             * @throws ProductNotFoundException
             * @throws InsufficientStockException
             */
            function () use ($id, $unpersistedProduct) {
                // Lock the product row for the duration of the transaction to
                // prevent concurrent updates from causing stock inconsistencies.
                $lockedModel = $this->repository->findByIdForUpdate($id);

                if ($lockedModel === null) {
                    throw new ProductNotFoundException($id);
                }

                $previousQuantity = $lockedModel->quantity;
                $newQuantity = $unpersistedProduct->quantity;

                if ($newQuantity < 0) {
                    throw new InsufficientStockException($id, $newQuantity, $previousQuantity);
                }

                $updated = $this->repository->update($id, $unpersistedProduct);

                if ($newQuantity !== $previousQuantity) {
                    $this->inventoryHistoryRepository->record(new UnpersistedInventoryHistoryEntry(
                        productId: $updated->id,
                        changeType: 'adjustment',
                        quantityChanged: $newQuantity - $previousQuantity,
                        previousQuantity: $previousQuantity,
                        newQuantity: $newQuantity,
                    ));
                }

                return $updated;
            }
        );

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
