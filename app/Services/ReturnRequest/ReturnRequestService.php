<?php

namespace App\Services\ReturnRequest;

use App\Dto\InventoryHistory\UnpersistedInventoryHistoryEntry;
use App\Dto\Order\UnpersistedOrder;
use App\Dto\ReturnRequest\ReturnRequest;
use App\Dto\ReturnRequest\UnpersistedReturnRequest;
use App\Enums\InventoryChangeType;
use App\Enums\OrderStatus;
use App\Enums\ReturnRequestStatus;
use App\Exceptions\InvalidReturnRequestStateException;
use App\Exceptions\OrderNotFoundException;
use App\Exceptions\ReturnRequestNotFoundException;
use App\Repositories\InventoryHistory\InventoryHistoryRepositoryInterface;
use App\Repositories\Order\OrderRepositoryInterface;
use App\Repositories\Product\ProductRepositoryInterface;
use App\Repositories\ReturnRequest\ReturnRequestRepositoryInterface;
use App\Services\AuditLogger;
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

        $allowedStatuses = [OrderStatus::Delivered, OrderStatus::Paid, OrderStatus::Shipped];
        if (! in_array($order->status, $allowedStatuses, true)) {
            $allowed = implode(', ', array_map(static fn (OrderStatus $s) => $s->value, $allowedStatuses));

            throw new InvalidReturnRequestStateException(
                "Cannot create return request for order with status '{$order->status->value}'. "
                ."Order must be in one of: {$allowed}.",
            );
        }

        $existing = $this->repository->findByOrderId($returnRequest->orderId);
        if ($existing !== null && $existing->status !== ReturnRequestStatus::Rejected) {
            throw new InvalidReturnRequestStateException(
                'A return request already exists for this order.',
            );
        }

        $created = $this->repository->persist($returnRequest);

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
        $returnRequest = $this->repository->findById($id);

        if ($returnRequest === null) {
            throw new ReturnRequestNotFoundException($id);
        }

        if ($returnRequest->status !== ReturnRequestStatus::Pending) {
            throw new InvalidReturnRequestStateException(
                "Cannot approve return request: current status is '{$returnRequest->status->value}', expected '".ReturnRequestStatus::Pending->value."'.",
            );
        }

        /** @var ReturnRequest $approved */
        $approved = DB::transaction(function () use ($id, $adminNotes, $returnRequest) {
            $approved = $this->repository->updateStatus($id, ReturnRequestStatus::Approved, $adminNotes);

            $order = $this->orderRepository->findById($returnRequest->orderId);

            if ($order !== null) {
                foreach ($order->items as $item) {
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

                $this->orderRepository->update($order->id, new UnpersistedOrder(
                    userId: $order->userId,
                    status: OrderStatus::Refunded,
                    totalPrice: $order->totalPrice,
                    items: [],
                ));
            }

            return $approved;
        });

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
        $returnRequest = $this->repository->findById($id);

        if ($returnRequest === null) {
            throw new ReturnRequestNotFoundException($id);
        }

        if ($returnRequest->status !== ReturnRequestStatus::Pending) {
            throw new InvalidReturnRequestStateException(
                "Cannot reject return request: current status is '{$returnRequest->status->value}', expected '".ReturnRequestStatus::Pending->value."'.",
            );
        }

        $rejected = $this->repository->updateStatus($id, ReturnRequestStatus::Rejected, $adminNotes);

        $this->auditLogger->log('return_request.rejected', 'return_request', $id, [
            'order_id' => $returnRequest->orderId,
            'admin_notes' => $adminNotes,
        ]);

        return $rejected;
    }
}
