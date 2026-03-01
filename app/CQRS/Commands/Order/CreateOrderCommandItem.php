<?php

namespace App\CQRS\Commands\Order;

final readonly class CreateOrderCommandItem
{
    public function __construct(
        public int   $productId,
        public int   $quantity,
        public float $unitPrice,
    ) {}
}

