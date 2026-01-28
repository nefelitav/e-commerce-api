<?php

namespace App\Dto\Order;

use App\Models\Order\OrderModel;

final readonly class Order
{
    /**
     * @param array<int, OrderItem> $items
     */
    public function __construct(
        public int $id,
        public int $userId,
        public string $status,
        public float $totalPrice,
        public array $items = [],
    ) {}

    public static function fromModel(OrderModel $order): self
    {
        $items = [];
        if ($order->relationLoaded('items')) {
            foreach ($order->items as $item) {
                $items[] = OrderItem::fromModel($item);
            }
        }

        return new self(
            $order->id,
            $order->user_id,
            $order->status,
            $order->total_price,
            $items,
        );
    }
}

