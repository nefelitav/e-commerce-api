<?php

namespace App\CQRS\Commands\Order;

use App\CQRS\Commands\CommandInterface;
use App\Enums\OrderStatus;

final readonly class CreateOrderCommand implements CommandInterface
{
    /**
     * @param array<int, CreateOrderCommandItem> $items
     */
    public function __construct(
        public int         $userId,
        public OrderStatus $status,
        public float       $totalPrice,
        public array       $items,
    ) {}
}


