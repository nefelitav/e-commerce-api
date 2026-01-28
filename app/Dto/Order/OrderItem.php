<?php

namespace App\Dto\Order;

use App\Models\Order\OrderItemModel;

final readonly class OrderItem
{
    public function __construct(
        public int $id,
        public int $orderId,
        public int $productId,
        public int $quantity,
        public float $unitPrice,
    ) {}

    public static function fromModel(OrderItemModel $item): self
    {
        return new self(
            $item->id,
            $item->order_id,
            $item->product_id,
            $item->quantity,
            $item->unit_price,
        );
    }
}

