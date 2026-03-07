<?php

namespace App\Services\Order;

use App\Dto\Order\Order;
use App\Enums\OrderStatus;
use App\Exceptions\InvalidOrderStateException;
use Carbon\Carbon;

final readonly class OrderStatusMachine implements OrderStatusMachineInterface
{
    private const CANCELLATION_WINDOW_HOURS = 24;

    /**
     * @var array<string, list<OrderStatus>>
     */
    private const USER_ALLOWED_TRANSITIONS = [
        OrderStatus::Pending->value => [OrderStatus::Cancelled],
        OrderStatus::PaymentFailed->value => [OrderStatus::Cancelled],
        OrderStatus::Paid->value => [OrderStatus::Cancelled],
    ];

    /**
     * @var array<string, list<OrderStatus>>
     */
    private const ADMIN_ALLOWED_TRANSITIONS = [
        OrderStatus::Pending->value => [OrderStatus::Paid, OrderStatus::Cancelled],
        OrderStatus::PaymentFailed->value => [OrderStatus::Paid, OrderStatus::Cancelled],
        OrderStatus::Paid->value => [OrderStatus::Processing, OrderStatus::Refunded, OrderStatus::Cancelled],
        OrderStatus::Processing->value => [OrderStatus::Shipped, OrderStatus::Cancelled],
        OrderStatus::Shipped->value => [OrderStatus::Delivered],
        OrderStatus::Delivered->value => [OrderStatus::Refunded],
    ];

    /**
     * Transitions that external systems (payment provider, shipping carrier) can trigger via webhooks.
     *
     * @var array<string, list<OrderStatus>>
     */
    private const WEBHOOK_ALLOWED_TRANSITIONS = [
        OrderStatus::Pending->value => [OrderStatus::Paid, OrderStatus::PaymentFailed],
        OrderStatus::PaymentFailed->value => [OrderStatus::Paid],
        OrderStatus::Processing->value => [OrderStatus::Shipped],
        OrderStatus::Shipped->value => [OrderStatus::Delivered],
    ];

    /**
     * @throws InvalidOrderStateException
     */
    public function assertUserTransitionAllowed(Order $existing, OrderStatus $newStatus): void
    {
        $allowed = self::USER_ALLOWED_TRANSITIONS[$existing->status->value] ?? [];

        if (! in_array($newStatus, $allowed, strict: true)) {
            throw new InvalidOrderStateException(
                "Transition from '{$existing->status->value}' to '{$newStatus->value}' is not allowed.",
            );
        }

        $this->assertWithinCancellationWindow($existing);
    }

    /**
     * @throws InvalidOrderStateException
     */
    private function assertWithinCancellationWindow(Order $existing): void
    {
        $hoursElapsed = Carbon::parse($existing->createdAt)->diffInHours(Carbon::now());

        if ($hoursElapsed >= self::CANCELLATION_WINDOW_HOURS) {
            throw new InvalidOrderStateException(
                'Orders can only be cancelled within '.self::CANCELLATION_WINDOW_HOURS.' hours of creation.',
            );
        }
    }

    /**
     * @throws InvalidOrderStateException
     */
    public function assertAdminTransitionAllowed(Order $existing, OrderStatus $newStatus): void
    {
        $allowed = self::ADMIN_ALLOWED_TRANSITIONS[$existing->status->value] ?? [];

        if (! in_array($newStatus, $allowed, strict: true)) {
            throw new InvalidOrderStateException(
                "Transition from '{$existing->status->value}' to '{$newStatus->value}' is not allowed.",
            );
        }
    }

    /**
     * @throws InvalidOrderStateException
     */
    public function assertWebhookTransitionAllowed(Order $existing, OrderStatus $newStatus): void
    {
        $allowed = self::WEBHOOK_ALLOWED_TRANSITIONS[$existing->status->value] ?? [];

        if (! in_array($newStatus, $allowed, strict: true)) {
            throw new InvalidOrderStateException(
                "Webhook transition from '{$existing->status->value}' to '{$newStatus->value}' is not allowed.",
            );
        }
    }
}
