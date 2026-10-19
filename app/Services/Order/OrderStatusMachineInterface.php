<?php

namespace App\Services\Order;

use App\Dto\Order\Order;
use App\Enums\OrderStatus;
use App\Exceptions\InvalidOrderStateException;

interface OrderStatusMachineInterface
{
    /**
     * @throws InvalidOrderStateException
     */
    public function assertUserTransitionAllowed(Order $existing, OrderStatus $newStatus): void;

    /**
     * @throws InvalidOrderStateException
     */
    public function assertAdminTransitionAllowed(Order $existing, OrderStatus $newStatus): void;

    /**
     * @throws InvalidOrderStateException
     */
    public function assertWebhookTransitionAllowed(Order $existing, OrderStatus $newStatus): void;

    /**
     * Whether cancelling the order refunds the customer (and so gives the stock back).
     */
    public function isRefundable(Order $order): bool;

    /**
     * Whether the order is in a state that can have a return request (created or approved).
     *
     * @throws InvalidOrderStateException
     */
    public function assertReturnAllowed(Order $order): void;
}
