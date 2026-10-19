<?php

namespace App\Services\Order;

use App\Dto\InventoryHistory\UnpersistedInventoryHistoryEntry;
use App\Dto\Order\Order;
use App\Dto\Order\OrderItem;
use App\Dto\Order\UnpersistedOrder;
use App\Dto\Order\UnpersistedOrderItem;
use App\Enums\InventoryChangeType;
use App\Enums\OrderStatus;
use App\Events\OrderCancelledEvent;
use App\Events\OrderCreatedEvent;
use App\Events\OrderDeliveredEvent;
use App\Events\OrderPaidEvent;
use App\Events\OrderShippedEvent;
use App\Exceptions\CouponNotFoundException;
use App\Exceptions\InsufficientStockException;
use App\Exceptions\InvalidCouponException;
use App\Exceptions\InvalidOrderStateException;
use App\Exceptions\OrderNotFoundException;
use App\Exceptions\ProductNotFoundException;
use App\Repositories\InventoryHistory\InventoryHistoryRepositoryInterface;
use App\Repositories\Order\OrderRepositoryInterface;
use App\Repositories\Product\ProductRepositoryInterface;
use App\Services\AuditLogger;
use App\Services\Coupon\CouponServiceInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

final readonly class OrderService implements OrderServiceInterface
{
    public function __construct(
        private OrderRepositoryInterface $repository,
        private ProductRepositoryInterface $productRepository,
        private InventoryHistoryRepositoryInterface $inventoryHistoryRepository,
        private OrderStatusMachineInterface $statusMachine,
        private CouponServiceInterface $couponService,
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
        if (($replayed = $this->findReplay($unpersistedOrder)) !== null) {
            return $replayed;
        }

        try {
            /** @var Order $order */
            $order = DB::transaction(function () use ($unpersistedOrder) {
                $this->adjustStock($unpersistedOrder->items, deduct: true);

                return $this->repository->persist($this->withRedeemedCoupon($unpersistedOrder));
            });
        } catch (UniqueConstraintViolationException $e) {
            // A concurrent retry with the same Idempotency-Key committed first: return its order.
            return $this->findReplay($unpersistedOrder) ?? throw $e;
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
        [$existing, $updated] = $this->transitionViaWebhook($orderId, OrderStatus::Paid);

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
        [$existing, $updated] = $this->transitionViaWebhook($orderId, OrderStatus::PaymentFailed);

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
        [$existing, $updated] = $this->transitionViaWebhook($orderId, OrderStatus::Shipped);

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
        [$existing, $updated] = $this->transitionViaWebhook($orderId, OrderStatus::Delivered);

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
        return $unpersistedOrder->status === OrderStatus::Cancelled
            ? $this->cancelOrder($id, $asAdmin)
            : $this->applyUpdate($id, $unpersistedOrder, $asAdmin);
    }

    /**
     * @throws OrderNotFoundException
     */
    public function deleteOrder(int $id): bool
    {
        /** @var Order $order */
        $order = DB::transaction(function () use ($id) {
            $order = $this->lockOrder($id);

            $this->restoreStock($order);

            // A cancelled order already released its coupon use.
            if ($order->couponId !== null && $order->status !== OrderStatus::Cancelled) {
                $this->couponService->releaseCoupon($order->couponId);
            }

            $this->repository->delete($id);

            return $order;
        });

        Cache::tags(['products'])->flush();

        $this->auditLogger->log('order.deleted', 'order', $id, [
            'restored_items' => count($order->items),
        ]);

        return true;
    }

    /**
     * @throws OrderNotFoundException
     * @throws InvalidOrderStateException
     */
    private function cancelOrder(int $id, bool $asAdmin): Order
    {
        /** @var array{0: Order, 1: Order, 2: bool} $result */
        $result = DB::transaction(function () use ($id, $asAdmin) {
            $existing = $this->lockAndAssertTransition($id, OrderStatus::Cancelled, $asAdmin);

            $refundIssued = $this->statusMachine->isRefundable($existing);

            if ($refundIssued) {
                $this->restoreStock($existing);
            }

            if ($existing->couponId !== null) {
                $this->couponService->releaseCoupon($existing->couponId);
            }

            return [$existing, $this->updateStatus($id, $existing, OrderStatus::Cancelled), $refundIssued];
        });
        [$existing, $updated, $refundIssued] = $result;

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

    /**
     * @throws OrderNotFoundException
     * @throws InvalidOrderStateException
     */
    private function applyUpdate(int $id, UnpersistedOrder $unpersistedOrder, bool $asAdmin): Order
    {
        /** @var array{0: Order, 1: Order} $result */
        $result = DB::transaction(function () use ($id, $unpersistedOrder, $asAdmin) {
            $existing = $this->lockAndAssertTransition($id, $unpersistedOrder->status, $asAdmin);

            return [$existing, $this->repository->update($id, $unpersistedOrder)];
        });
        [$existing, $updated] = $result;

        $this->auditLogger->log('order.updated', 'order', $id, [
            'previous_status' => $existing->status,
            'new_status' => $unpersistedOrder->status,
            'total_price' => $unpersistedOrder->totalPrice,
            'as_admin' => $asAdmin,
        ]);

        match ($unpersistedOrder->status) {
            OrderStatus::Shipped => OrderShippedEvent::dispatch($updated),
            OrderStatus::Delivered => OrderDeliveredEvent::dispatch($updated),
            default => null,
        };

        return $updated;
    }

    /**
     * Lock the order, then re-check the webhook transition inside the transaction so duplicate
     * or racing webhooks cannot both pass the state machine.
     *
     * @return array{0: Order, 1: Order} the order before and after the transition
     *
     * @throws OrderNotFoundException
     * @throws InvalidOrderStateException
     */
    private function transitionViaWebhook(int $orderId, OrderStatus $to): array
    {
        /** @var array{0: Order, 1: Order} $result */
        $result = DB::transaction(function () use ($orderId, $to) {
            $existing = $this->lockOrder($orderId);

            $this->statusMachine->assertWebhookTransitionAllowed($existing, $to);

            return [$existing, $this->updateStatus($orderId, $existing, $to)];
        });

        return $result;
    }

    /**
     * Must run inside a transaction: two concurrent updates (e.g. a double cancel) must not
     * both see the old status.
     *
     * @throws OrderNotFoundException
     * @throws InvalidOrderStateException
     */
    private function lockAndAssertTransition(int $id, OrderStatus $to, bool $asAdmin): Order
    {
        $existing = $this->lockOrder($id);

        if ($asAdmin) {
            $this->statusMachine->assertAdminTransitionAllowed($existing, $to);
        } else {
            $this->statusMachine->assertUserTransitionAllowed($existing, $to);
        }

        return $existing;
    }

    /**
     * @throws OrderNotFoundException
     */
    private function lockOrder(int $id): Order
    {
        return $this->repository->findByIdForUpdate($id) ?? throw new OrderNotFoundException($id);
    }

    /**
     * Reads shared state, so repeated calls can differ (a concurrent request may commit in between).
     *
     * @phpstan-impure
     */
    private function findReplay(UnpersistedOrder $order): ?Order
    {
        if ($order->idempotencyKey === null) {
            return null;
        }

        return $this->repository->findByIdempotencyKey($order->userId, $order->idempotencyKey);
    }

    /**
     * Atomically consume a coupon use (when one was supplied) and record the discount on the order.
     *
     * @throws CouponNotFoundException
     * @throws InvalidCouponException
     */
    private function withRedeemedCoupon(UnpersistedOrder $order): UnpersistedOrder
    {
        if ($order->couponCode === null) {
            return $order;
        }

        $coupon = $this->couponService->redeemCoupon($order->couponCode, $order->totalPrice);

        return $order->withCoupon($coupon->id, $this->couponService->calculateDiscount($coupon, $order->totalPrice));
    }

    private function restoreStock(Order $order): void
    {
        if ($order->stockRestored) {
            return;
        }

        $this->adjustStock($order->items, deduct: false);

        $this->repository->markStockRestored($order->id);
    }

    /**
     * Take (sale) or give back (return) stock for each item, recording inventory history.
     * Must run inside a transaction.
     *
     * @param  array<int, UnpersistedOrderItem|OrderItem>  $items
     *
     * @throws ProductNotFoundException
     * @throws InsufficientStockException
     */
    private function adjustStock(array $items, bool $deduct): void
    {
        // Lock products in a stable (id) order so two orders containing the same products
        // in a different sequence cannot deadlock each other.
        usort($items, static fn ($a, $b) => $a->productId <=> $b->productId);

        foreach ($items as $item) {
            $productModel = $this->productRepository->findByIdForUpdate($item->productId);

            if ($productModel === null) {
                if ($deduct) {
                    throw new ProductNotFoundException($item->productId);
                }

                // Product was deleted since the sale: nothing to give the stock back to.
                continue;
            }

            $previousQuantity = $productModel->quantity;
            $newQuantity = $deduct ? $previousQuantity - $item->quantity : $previousQuantity + $item->quantity;

            if ($newQuantity < 0) {
                throw new InsufficientStockException($item->productId, $item->quantity, $previousQuantity);
            }

            $productModel->update(['quantity' => $newQuantity]);

            $this->inventoryHistoryRepository->record(new UnpersistedInventoryHistoryEntry(
                productId: $item->productId,
                changeType: $deduct ? InventoryChangeType::Sale : InventoryChangeType::Return,
                quantityChanged: $newQuantity - $previousQuantity,
                previousQuantity: $previousQuantity,
                newQuantity: $newQuantity,
            ));
        }
    }

    private function updateStatus(int $orderId, Order $existing, OrderStatus $newStatus): Order
    {
        return $this->repository->update($orderId, new UnpersistedOrder(
            userId: $existing->userId,
            status: $newStatus,
            totalPrice: $existing->totalPrice,
            items: [],
        ));
    }
}
