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
    private const REFUNDABLE_STATUSES = [
        OrderStatus::Paid->value,
        OrderStatus::Processing->value,
    ];

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
        $idempotencyKey = $unpersistedOrder->idempotencyKey;

        if ($idempotencyKey !== null) {
            $replayed = $this->repository->findByIdempotencyKey($unpersistedOrder->userId, $idempotencyKey);

            if ($replayed !== null) {
                return $replayed;
            }
        }

        try {
            /** @var Order $order */
            $order = DB::transaction(
                /**
                 * @throws ProductNotFoundException
                 * @throws InsufficientStockException
                 * @throws CouponNotFoundException
                 * @throws InvalidCouponException
                 */
                function () use ($unpersistedOrder) {
                    // Lock products in a stable (id) order so two orders containing the
                    // same products in a different sequence cannot deadlock each other.
                    $lockOrder = $unpersistedOrder->items;
                    usort($lockOrder, static fn ($a, $b) => $a->productId <=> $b->productId);

                    foreach ($lockOrder as $item) {
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

                    return $this->repository->persist($this->withRedeemedCoupon($unpersistedOrder));
                },
            );
        } catch (UniqueConstraintViolationException $e) {
            // A concurrent retry with the same Idempotency-Key committed first: return its order.
            if ($idempotencyKey !== null) {
                $replayed = $this->repository->findByIdempotencyKey($unpersistedOrder->userId, $idempotencyKey);

                if ($replayed !== null) {
                    return $replayed;
                }
            }

            throw $e;
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
        /** @var array{0: Order, 1: Order} $result */
        $result = DB::transaction(function () use ($orderId) {
            // Lock + re-check inside the transaction so duplicate / racing webhooks
            // cannot both pass the state machine.
            $existing = $this->lockOrder($orderId);

            $this->statusMachine->assertWebhookTransitionAllowed($existing, OrderStatus::Paid);

            return [$existing, $this->updateStatus($orderId, $existing, OrderStatus::Paid)];
        });
        [$existing, $updated] = $result;

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
        /** @var array{0: Order, 1: Order} $result */
        $result = DB::transaction(function () use ($orderId) {
            // Lock + re-check inside the transaction so duplicate / racing webhooks
            // cannot both pass the state machine.
            $existing = $this->lockOrder($orderId);

            $this->statusMachine->assertWebhookTransitionAllowed($existing, OrderStatus::PaymentFailed);

            return [$existing, $this->updateStatus($orderId, $existing, OrderStatus::PaymentFailed)];
        });
        [$existing, $updated] = $result;

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
        /** @var array{0: Order, 1: Order} $result */
        $result = DB::transaction(function () use ($orderId) {
            // Lock + re-check inside the transaction so duplicate / racing webhooks
            // cannot both pass the state machine.
            $existing = $this->lockOrder($orderId);

            $this->statusMachine->assertWebhookTransitionAllowed($existing, OrderStatus::Shipped);

            return [$existing, $this->updateStatus($orderId, $existing, OrderStatus::Shipped)];
        });
        [$existing, $updated] = $result;

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
        /** @var array{0: Order, 1: Order} $result */
        $result = DB::transaction(function () use ($orderId) {
            // Lock + re-check inside the transaction so duplicate / racing webhooks
            // cannot both pass the state machine.
            $existing = $this->lockOrder($orderId);

            $this->statusMachine->assertWebhookTransitionAllowed($existing, OrderStatus::Delivered);

            return [$existing, $this->updateStatus($orderId, $existing, OrderStatus::Delivered)];
        });
        [$existing, $updated] = $result;

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
        /** @var array{0: Order, 1: Order, 2: bool} $result */
        $result = DB::transaction(function () use ($id, $unpersistedOrder, $asAdmin) {
            // Lock + validate inside the transaction: two concurrent updates (e.g. a double
            // cancel) must not both see the old status and both restore stock.
            $existing = $this->lockOrder($id);

            if ($asAdmin) {
                $this->statusMachine->assertAdminTransitionAllowed($existing, $unpersistedOrder->status);
            } else {
                $this->statusMachine->assertUserTransitionAllowed($existing, $unpersistedOrder->status);
            }

            if ($unpersistedOrder->status === OrderStatus::Cancelled) {
                $refundIssued = in_array($existing->status->value, self::REFUNDABLE_STATUSES, true);

                if ($refundIssued) {
                    $this->restoreStock($existing);
                }

                if ($existing->couponId !== null) {
                    $this->couponService->releaseCoupon($existing->couponId);
                }

                return [$existing, $this->updateStatus($id, $existing, OrderStatus::Cancelled), $refundIssued];
            }

            return [$existing, $this->repository->update($id, $unpersistedOrder), false];
        });
        [$existing, $updated, $refundIssued] = $result;

        if ($unpersistedOrder->status === OrderStatus::Cancelled) {
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
        /** @var Order $order */
        $order = DB::transaction(function () use ($id) {
            $order = $this->lockOrder($id);

            $this->restoreStock($order);

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
     */
    private function lockOrder(int $id): Order
    {
        $order = $this->repository->findByIdForUpdate($id);

        if ($order === null) {
            throw new OrderNotFoundException($id);
        }

        return $order;
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

        return new UnpersistedOrder(
            userId: $order->userId,
            status: $order->status,
            totalPrice: $order->totalPrice,
            items: $order->items,
            couponCode: $order->couponCode,
            couponId: $coupon->id,
            discountAmount: $this->couponService->calculateDiscount($coupon, $order->totalPrice),
            idempotencyKey: $order->idempotencyKey,
        );
    }

    private function restoreStock(Order $order): void
    {
        if ($order->stockRestored) {
            return;
        }

        // Stable (product id) lock order, same as order creation, to avoid deadlocks.
        $items = $order->items;
        usort($items, static fn ($a, $b) => $a->productId <=> $b->productId);

        foreach ($items as $item) {
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

        $this->repository->markStockRestored($order->id);
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
