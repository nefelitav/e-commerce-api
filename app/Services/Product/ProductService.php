<?php

namespace App\Services\Product;

use App\Dto\InventoryHistory\UnpersistedInventoryHistoryEntry;
use App\Dto\Product\Product;
use App\Dto\Product\UnpersistedProduct;
use App\Enums\InventoryChangeType;
use App\Exceptions\InsufficientStockException;
use App\Exceptions\ProductAlreadyExistsException;
use App\Exceptions\ProductNotFoundException;
use App\Exceptions\ProductVersionConflictException;
use App\Repositories\InventoryHistory\InventoryHistoryRepositoryInterface;
use App\Repositories\Product\ProductRepositoryInterface;
use App\Services\AuditLogger;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

final readonly class ProductService implements ProductServiceInterface
{
    public function __construct(
        private ProductRepositoryInterface $repository,
        private InventoryHistoryRepositoryInterface $inventoryHistoryRepository,
        private AuditLogger $auditLogger,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @param  array<string>  $includes
     * @return LengthAwarePaginator<int, Product>
     */
    public function listProducts(
        int $page = 1,
        int $perPage = 15,
        string $sort = 'id',
        string $order = 'asc',
        array $filters = [],
        array $includes = [],
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
                try {
                    $created = $this->repository->persist($unpersistedProduct);
                } catch (UniqueConstraintViolationException) {
                    throw new ProductAlreadyExistsException($unpersistedProduct->name);
                }

                $this->inventoryHistoryRepository->record(new UnpersistedInventoryHistoryEntry(
                    productId: $created->id,
                    changeType: InventoryChangeType::Addition,
                    quantityChanged: $created->quantity,
                    previousQuantity: 0,
                    newQuantity: $created->quantity,
                ));

                return $created;
            },
        );

        Cache::tags(['products'])->flush();

        $this->auditLogger->log('product.created', 'product', $created->id, [
            'name' => $created->name,
            'price' => $created->price,
            'quantity' => $created->quantity,
        ]);

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

                // Optimistic locking: reject edits based on a stale copy so a concurrent
                // sale / edit is not silently overwritten. Omitting `version` opts out.
                if ($unpersistedProduct->expectedVersion !== null
                    && $unpersistedProduct->expectedVersion !== $lockedModel->version
                ) {
                    throw new ProductVersionConflictException(
                        $id,
                        $unpersistedProduct->expectedVersion,
                        $lockedModel->version,
                    );
                }

                $previousQuantity = $lockedModel->quantity;
                $newQuantity = $unpersistedProduct->quantity;

                if ($newQuantity < 0) {
                    throw new InsufficientStockException($id, $newQuantity, $previousQuantity);
                }

                try {
                    $updated = $this->repository->update($id, $unpersistedProduct);
                } catch (UniqueConstraintViolationException) {
                    throw new ProductAlreadyExistsException($unpersistedProduct->name);
                }

                if ($newQuantity !== $previousQuantity) {
                    $this->inventoryHistoryRepository->record(new UnpersistedInventoryHistoryEntry(
                        productId: $updated->id,
                        changeType: InventoryChangeType::Adjustment,
                        quantityChanged: $newQuantity - $previousQuantity,
                        previousQuantity: $previousQuantity,
                        newQuantity: $newQuantity,
                    ));
                }

                return $updated;
            },
        );

        Cache::tags(['products'])->flush();

        $this->auditLogger->log('product.updated', 'product', $id, [
            'name' => $updated->name,
            'price' => $updated->price,
            'quantity' => $updated->quantity,
        ]);

        return $updated;
    }

    /**
     * @throws ProductNotFoundException
     */
    public function deleteProduct(int $id): bool
    {
        $result = $this->repository->delete($id);

        Cache::tags(['products'])->flush();

        $this->auditLogger->log('product.deleted', 'product', $id);

        return $result;
    }
}
