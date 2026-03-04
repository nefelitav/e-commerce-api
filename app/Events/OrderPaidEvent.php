<?php

namespace App\Events;

use App\Dto\Order\Order;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class OrderPaidEvent
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Order $order,
        public readonly string $occurredAt,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toPayload(): array
    {
        $items = array_map(static fn($item) => [
            'product_id' => $item->productId,
            'quantity' => $item->quantity,
            'unit_price' => $item->unitPrice,
        ], $this->order->items);

        return [
            'event' => 'order.paid',
            'occurred_at' => $this->occurredAt,
            'data' => [
                'order_id' => $this->order->id,
                'user_id' => $this->order->userId,
                'status' => $this->order->status->value,
                'total_price' => $this->order->totalPrice,
                'items' => $items,
            ],
        ];
    }
}

