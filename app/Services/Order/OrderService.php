<?php

namespace App\Services\Order;

use App\Dto\InventoryHistory\UnpersistedInventoryHistoryEntry;
use App\Dto\Order\Order;
use App\Dto\Order\UnpersistedOrder;
use App\Enums\InventoryChangeType;
use App\Enums\OrderStatus;
use App\Events\OrderCancelledEvent;
use App\Events\OrderCreatedEvent;
use App\Events\OrderDeliveredEvent;
use App\Events\OrderPaidEvent;
use App\Events\OrderShippedEvent;
use App\Exceptions\InsufficientStockException;
use App\Exceptions\InvalidOrderStateException;
use App\Exceptions\OrderNotFoundException;
use App\Exceptions\ProductNotFoundException;
use App\Repositories\InventoryHistory\InventoryHistoryRepositoryInterface;
use App\Repositories\Order\OrderRepositoryInterface;
use App\Repositories\Product\ProductRepositoryInterface;
use App\Services\AuditLogger;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

final readonly class OrderService implements OrderServiceInterface
{
    private const REFUNDABLE_STATUSES = [
        OrderStatus::Paid->value,
        OrderStatus::Processing->value,
    ];

    public function __construct(
        private OrderRepositoryInterface $repository,
        private ProductRepositoryInterface $productRepository,
        private InventoryHistoryRepositoryInterface $inventoryHistoryRepository,
        private OrderStatusMachineInterface $statusMachine,
        private AuditLogger $auditLogger,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @param  array<string>  $includes
     * @return LengthAwarePaginator<int, Order>
     */
    public function listOrders(
        int $page = 1,
        int $perPage = 15,
        string $sort = 'id',
        string $order = 'asc',
        array $filters = [],
        array $includes = [],
    ): LengthAwarePaginator {
        return $this->repository->getAll($page, $perPage, $sort, $order, $filters, $includes);
    }

    public function getOrderById(int $id): ?Order
    {
        return $this->repository->findById($id);
    }

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
                        changeType: InventoryChangeType::Sale,
                        quantityChanged: -$item->quantity,
                        previousQuantity: $previousQuantity,
                        newQuantity: $newQuantity,
                    ));
                }

                return $this->repository->persist($unpersistedOrder);
            },
        );

        foreach ($unpersistedOrder->items as $item) {
            Cache::forget("products.{$item->productId}");
        }
        Cache::tags(['products'])->flush();

        $this->auditLogger->log('order.created', 'order', $order->id, [
            'status' => $order->status,
            'total_price' => $order->totalPrice,
            'item_count' => count($order->items),
        ]);

        OrderCreatedEvent::dispatch($order);

        return $order;
    }

    /**
     * @throws OrderNotFoundException
     * @throws InvalidOrderStateException
     */
    public function markOrderAsPaid(int $orderId, string $paymentReference): Order
    {
        $existing = $this->repository->findById($orderId);

        if ($existing === null) {
            throw new OrderNotFoundException($orderId);
        }

        $this->statusMachine->assertWebhookTransitionAllowed($existing, OrderStatus::Paid);

        $updated = $this->updateStatus($orderId, $existing, OrderStatus::Paid);

        $this->auditLogger->log('order.paid', 'order', $orderId, [
            'payment_reference' => $paymentReference,
            'previous_status' => $existing->status->value,
            'total_price' => $existing->totalPrice,
        ]);

        OrderPaidEvent::dispatch($updated, now()->toIso8601String());

        return $updated;
    }

    /**
     * @throws OrderNotFoundException
     * @throws InvalidOrderStateException
     */
    public function markOrderAsPaymentFailed(int $orderId, string $paymentReference): Order
    {
        $existing = $this->repository->findById($orderId);

        if ($existing === null) {
            throw new OrderNotFoundException($orderId);
        }

        $this->statusMachine->assertWebhookTransitionAllowed($existing, OrderStatus::PaymentFailed);

        $updated = $this->updateStatus($orderId, $existing, OrderStatus::PaymentFailed);

        $this->auditLogger->log('order.payment_failed', 'order', $orderId, [
            'payment_reference' => $paymentReference,
            'previous_status' => $existing->status->value,
        ]);

        return $updated;
    }

    /**
     * @throws OrderNotFoundException
     * @throws InvalidOrderStateException
     */
    public function markOrderAsShipped(int $orderId, string $trackingNumber): Order
    {
        $existing = $this->repository->findById($orderId);

        if ($existing === null) {
            throw new OrderNotFoundException($orderId);
        }

        $this->statusMachine->assertWebhookTransitionAllowed($existing, OrderStatus::Shipped);

        $updated = $this->updateStatus($orderId, $existing, OrderStatus::Shipped);

        $this->auditLogger->log('order.shipped', 'order', $orderId, [
            'tracking_number' => $trackingNumber,
            'previous_status' => $existing->status->value,
        ]);

        OrderShippedEvent::dispatch($updated);

        return $updated;
    }

    /**
     * @throws OrderNotFoundException
     * @throws InvalidOrderStateException
     */
    public function markOrderAsDelivered(int $orderId): Order
    {
        $existing = $this->repository->findById($orderId);

        if ($existing === null) {
            throw new OrderNotFoundException($orderId);
        }

        $this->statusMachine->assertWebhookTransitionAllowed($existing, OrderStatus::Delivered);

        $updated = $this->updateStatus($orderId, $existing, OrderStatus::Delivered);

        $this->auditLogger->log('order.delivered', 'order', $orderId, [
            'previous_status' => $existing->status->value,
        ]);

        OrderDeliveredEvent::dispatch($updated);

        return $updated;
    }

    /**
     * @throws OrderNotFoundException
     * @throws InvalidOrderStateException
     */
    public function updateOrder(int $id, UnpersistedOrder $unpersistedOrder, bool $asAdmin = false): Order
    {
        $existing = $this->repository->findById($id);

        if ($existing === null) {
            throw new OrderNotFoundException($id);
        }

        if ($asAdmin) {
            $this->statusMachine->assertAdminTransitionAllowed($existing, $unpersistedOrder->status);
        } else {
            $this->statusMachine->assertUserTransitionAllowed($existing, $unpersistedOrder->status);
        }

        if ($unpersistedOrder->status === OrderStatus::Cancelled) {
            return $this->cancelOrder($id, $existing, $asAdmin);
        }

        $updated = $this->repository->update($id, $unpersistedOrder);

        $this->auditLogger->log('order.updated', 'order', $id, [
            'previous_status' => $existing->status,
            'new_status' => $unpersistedOrder->status,
            'total_price' => $unpersistedOrder->totalPrice,
            'as_admin' => $asAdmin,
        ]);

        if ($unpersistedOrder->status === OrderStatus::Shipped) {
            OrderShippedEvent::dispatch($updated);
        }

        if ($unpersistedOrder->status === OrderStatus::Delivered) {
            OrderDeliveredEvent::dispatch($updated);
        }

        return $updated;
    }

    /**
     * @throws OrderNotFoundException
     */
    public function deleteOrder(int $id): bool
    {
        $order = $this->repository->findById($id);

        if ($order === null) {
            throw new OrderNotFoundException($id);
        }

        DB::transaction(function () use ($order, $id) {
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

            $this->repository->delete($id);
        });

        foreach ($order->items as $item) {
            Cache::forget("products.{$item->productId}");
        }
        Cache::tags(['products'])->flush();

        $this->auditLogger->log('order.deleted', 'order', $id, [
            'restored_items' => count($order->items),
        ]);

        return true;
    }

    private function cancelOrder(int $id, Order $existing, bool $asAdmin): Order
    {
        $refundIssued = in_array($existing->status->value, self::REFUNDABLE_STATUSES, true);

        /** @var Order $updated */
        $updated = DB::transaction(function () use ($id, $existing, $refundIssued) {
            if ($refundIssued) {
                $this->restoreStock($existing);
            }

            return $this->updateStatus($id, $existing, OrderStatus::Cancelled);
        });

        if ($refundIssued) {
            Cache::tags(['products'])->flush();
        }

        $this->auditLogger->log('order.cancelled', 'order', $id, [
            'previous_status' => $existing->status->value,
            'refund_issued' => $refundIssued,
            'as_admin' => $asAdmin,
        ]);

        OrderCancelledEvent::dispatch($updated, $refundIssued);

        return $updated;
    }

    private function restoreStock(Order $order): void
    {
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
    }

    private function updateStatus(int $orderId, Order $existing, OrderStatus $newStatus): Order
    {
        $unpersisted = new UnpersistedOrder(
            userId: $existing->userId,
            status: $newStatus,
            totalPrice: $existing->totalPrice,
            items: [],
        );

        return $this->repository->update($orderId, $unpersisted);
    }
}
