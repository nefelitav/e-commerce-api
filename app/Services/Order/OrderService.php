<?php

namespace App\Services\Order;

use App\Dto\InventoryHistory\UnpersistedInventoryHistoryEntry;
use App\Dto\Order\Order;
use App\Dto\Order\UnpersistedOrder;
use App\Exceptions\InsufficientStockException;
use App\Exceptions\OrderNotFoundException;
use App\Exceptions\ProductNotFoundException;
use App\Repositories\InventoryHistory\InventoryHistoryRepository;
use App\Repositories\Order\OrderRepository;
use App\Repositories\Product\ProductRepository;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

final readonly class OrderService
{
    public function __construct(
        private OrderRepository $repository,
        private ProductRepository $productRepository,
        private InventoryHistoryRepository $inventoryHistoryRepository,
    ) {
    }

    /**
     * @param array<string, mixed> $filters
     * @param array<string> $includes
     * @return LengthAwarePaginator<int, Order>
     */
    public function listOrders(
        int $page = 1,
        int $perPage = 15,
        string $sort = 'id',
        string $order = 'asc',
        array $filters = [],
        array $includes = []
    ): LengthAwarePaginator {
        return $this->repository->getAll($page, $perPage, $sort, $order, $filters, $includes);
    }

    public function getOrderById(int $id): ?Order
    {
        return $this->repository->findById($id);
    }

    /**
     * Places an order, deducting stock for each item atomically.
     *
     * For every item in the order the product row is locked (FOR UPDATE),
     * stock availability is validated, and — once all items pass — the
     * product quantities are decremented and inventory history entries are
     * recorded, all within a single DB transaction.
     */
    public function createOrder(UnpersistedOrder $unpersistedOrder): Order
    {
        /** @var Order $order */
        $order = DB::transaction(
            /**
             * @throws ProductNotFoundException
             * @throws InsufficientStockException
             */
            function () use ($unpersistedOrder) {
                foreach ($unpersistedOrder->items as $item) {
                    // Acquire a pessimistic write lock to prevent overselling under
                    // concurrent requests for the same product.
                    $productModel = $this->productRepository->findByIdForUpdate($item->productId);

                    if ($productModel === null) {
                        throw new ProductNotFoundException($item->productId);
                    }

                    $previousQuantity = $productModel->quantity;

                    if ($previousQuantity < $item->quantity) {
                        throw new InsufficientStockException(
                            $item->productId,
                            $item->quantity,
                            $previousQuantity,
                        );
                    }

                    $newQuantity = $previousQuantity - $item->quantity;

                    $productModel->update(['quantity' => $newQuantity]);

                    $this->inventoryHistoryRepository->record(new UnpersistedInventoryHistoryEntry(
                        productId: $item->productId,
                        changeType: 'sale',
                        quantityChanged: -$item->quantity,
                        previousQuantity: $previousQuantity,
                        newQuantity: $newQuantity,
                    ));
                }

                return $this->repository->persist($unpersistedOrder);
            }
        );

        return $order;
    }

    /**
     * @throws OrderNotFoundException
     */
    public function updateOrder(int $id, UnpersistedOrder $unpersistedOrder): Order
    {
        return $this->repository->update($id, $unpersistedOrder);
    }

    /**
     * @throws OrderNotFoundException
     */
    public function deleteOrder(int $id): bool
    {
        return $this->repository->delete($id);
    }
}

