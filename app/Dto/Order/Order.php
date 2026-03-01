<?php

namespace App\Dto\Order;

use App\Enums\OrderStatus;
use App\Models\Order\OrderModel;

final readonly class Order
{
    /**
     * @param array<int, OrderItem> $items
     */
    public function __construct(
        public int $id,
        public int $userId,
        public OrderStatus $status,
        public float $totalPrice,
        public string $createdAt,
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
            id: $order->id,
            userId: $order->user_id,
            status: $order->status,
            totalPrice: $order->total_price,
            createdAt: (string) $order->created_at,
            items: $items,
        );
    }
}

