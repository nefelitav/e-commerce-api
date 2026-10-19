<?php

namespace App\Services\ReturnRequest;

use App\Dto\InventoryHistory\UnpersistedInventoryHistoryEntry;
use App\Dto\Order\Order;
use App\Dto\Order\UnpersistedOrder;
use App\Dto\ReturnRequest\ReturnRequest;
use App\Dto\ReturnRequest\UnpersistedReturnRequest;
use App\Enums\InventoryChangeType;
use App\Enums\OrderStatus;
use App\Enums\ReturnRequestStatus;
use App\Exceptions\InvalidOrderStateException;
use App\Exceptions\InvalidReturnRequestStateException;
use App\Exceptions\OrderNotFoundException;
use App\Exceptions\ReturnRequestNotFoundException;
use App\Repositories\InventoryHistory\InventoryHistoryRepositoryInterface;
use App\Repositories\Order\OrderRepositoryInterface;
use App\Repositories\Product\ProductRepositoryInterface;
use App\Repositories\ReturnRequest\ReturnRequestRepositoryInterface;
use App\Services\AuditLogger;
use App\Services\Order\OrderStatusMachineInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

final readonly class ReturnRequestService implements ReturnRequestServiceInterface
{
    public function __construct(
        private ReturnRequestRepositoryInterface $repository,
        private OrderRepositoryInterface $orderRepository,
        private ProductRepositoryInterface $productRepository,
        private InventoryHistoryRepositoryInterface $inventoryHistoryRepository,
        private OrderStatusMachineInterface $statusMachine,
        private AuditLogger $auditLogger,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, ReturnRequest>
     */
    public function listReturnRequests(
        int $page = 1,
        int $perPage = 15,
        string $sort = 'id',
        string $order = 'asc',
        array $filters = [],
    ): LengthAwarePaginator {
        return $this->repository->getAll($page, $perPage, $sort, $order, $filters);
    }

    public function getReturnRequestById(int $id): ?ReturnRequest
    {
        return $this->repository->findById($id);
    }

    /**
     * @throws OrderNotFoundException
     * @throws InvalidReturnRequestStateException
     */
    public function createReturnRequest(UnpersistedReturnRequest $returnRequest): ReturnRequest
    {
        $order = $this->orderRepository->findById($returnRequest->orderId);

        if ($order === null) {
            throw new OrderNotFoundException($returnRequest->orderId);
        }

        $this->assertOrderReturnable($order);

        try {
            $created = $this->repository->persist($returnRequest);
        } catch (UniqueConstraintViolationException) {
            // Partial unique index: one non-rejected return request per order.
            throw new InvalidReturnRequestStateException(
                'A return request already exists for this order.',
            );
        }

        $this->auditLogger->log('return_request.created', 'return_request', $created->id, [
            'order_id' => $created->orderId,
            'user_id' => $created->userId,
            'reason' => $created->reason,
        ]);

        return $created;
    }

    /**
     * @throws ReturnRequestNotFoundException
     * @throws InvalidReturnRequestStateException
     */
    public function approveReturnRequest(int $id, ?string $adminNotes = null): ReturnRequest
    {
        /** @var array{0: ReturnRequest, 1: ReturnRequest} $result */
        $result = DB::transaction(function () use ($id, $adminNotes) {
            // Lock + re-check inside the transaction so two admins approving at once
            // cannot both restore stock.
            $returnRequest = $this->lockPending($id, 'approve');

            $order = $this->orderRepository->findByIdForUpdate($returnRequest->orderId);

            // The order may have been cancelled / refunded since the request was created.
            if ($order !== null) {
                $this->assertOrderReturnable($order);
            }

            $approved = $this->repository->updateStatus($id, ReturnRequestStatus::Approved, $adminNotes);

            if ($order !== null) {
                $restoreStock = ! $order->stockRestored;

                // Same stable lock order as order creation, to avoid deadlocks.
                $items = $order->items;
                usort($items, static fn ($a, $b) => $a->productId <=> $b->productId);

                foreach ($restoreStock ? $items : [] as $item) {
                    $productModel = $this->productRepository->findByIdForUpdate($item->productId);

                    if ($productModel !== null) {
                        $previousQuantity = $productModel->quantity;
                        $newQuantity = $previousQuantity + $item->quantity;

                        $productModel->update(['quantity' => $newQuantity]);

                        $this->inventoryHistoryRepository->record(new UnpersistedInventoryHistoryEntry(
                            productId: $item->productId,
                            changeType: InventoryChangeType::Return,
                            quantityChanged: $item->quantity,
                            previousQuantity: $previousQuantity,
                            newQuantity: $newQuantity,
                        ));
                    }
                }

                if ($restoreStock) {
                    $this->orderRepository->markStockRestored($order->id);
                }

                $this->orderRepository->update($order->id, new UnpersistedOrder(
                    userId: $order->userId,
                    status: OrderStatus::Refunded,
                    totalPrice: $order->totalPrice,
                    items: [],
                ));
            }

            return [$returnRequest, $approved];
        });
        [$returnRequest, $approved] = $result;

        Cache::tags(['products'])->flush();

        $this->auditLogger->log('return_request.approved', 'return_request', $id, [
            'order_id' => $returnRequest->orderId,
            'admin_notes' => $adminNotes,
        ]);

        return $approved;
    }

    /**
     * @throws ReturnRequestNotFoundException
     * @throws InvalidReturnRequestStateException
     */
    public function rejectReturnRequest(int $id, ?string $adminNotes = null): ReturnRequest
    {
        /** @var array{0: ReturnRequest, 1: ReturnRequest} $result */
        $result = DB::transaction(function () use ($id, $adminNotes) {
            $returnRequest = $this->lockPending($id, 'reject');

            return [$returnRequest, $this->repository->updateStatus($id, ReturnRequestStatus::Rejected, $adminNotes)];
        });
        [$returnRequest, $rejected] = $result;

        $this->auditLogger->log('return_request.rejected', 'return_request', $id, [
            'order_id' => $returnRequest->orderId,
            'admin_notes' => $adminNotes,
        ]);

        return $rejected;
    }

    /**
     * @throws ReturnRequestNotFoundException
     * @throws InvalidReturnRequestStateException
     */
    private function lockPending(int $id, string $action): ReturnRequest
    {
        $returnRequest = $this->repository->findByIdForUpdate($id);

        if ($returnRequest === null) {
            throw new ReturnRequestNotFoundException($id);
        }

        if ($returnRequest->status !== ReturnRequestStatus::Pending) {
            throw new InvalidReturnRequestStateException(
                "Cannot {$action} return request: current status is '{$returnRequest->status->value}', expected '".ReturnRequestStatus::Pending->value."'.",
            );
        }

        return $returnRequest;
    }

    /**
     * @throws InvalidReturnRequestStateException
     */
    private function assertOrderReturnable(Order $order): void
    {
        try {
            $this->statusMachine->assertReturnAllowed($order);
        } catch (InvalidOrderStateException $e) {
            throw new InvalidReturnRequestStateException($e->getMessage());
        }
    }
}
