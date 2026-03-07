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
}
