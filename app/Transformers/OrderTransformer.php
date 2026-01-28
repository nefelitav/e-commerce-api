<?php

namespace App\Transformers;

use App\Dto\Order\Order;

final readonly class OrderTransformer
{
    /**
     * @return array<string, mixed>
     */
    public function transform(Order $order): array
    {
        $items = [];
        foreach ($order->items as $item) {
            $items[] = [
                'id' => $item->id,
                'order_id' => $item->orderId,
                'product_id' => $item->productId,
                'quantity' => $item->quantity,
                'unit_price' => $item->unitPrice,
            ];
        }

        return [
            'id' => $order->id,
            'user_id' => $order->userId,
            'status' => $order->status,
            'total_price' => $order->totalPrice,
            'items' => $items,
        ];
    }
}

