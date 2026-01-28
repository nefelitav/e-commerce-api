<?php

namespace App\Transformers;

use App\Dto\Cart\Cart;

final readonly class CartTransformer
{
    /**
     * @return array<string, mixed>
     */
    public function transform(Cart $cart): array
    {
        $items = [];
        foreach ($cart->items as $item) {
            $items[] = [
                'id' => $item->id,
                'cart_id' => $item->cartId,
                'product_id' => $item->productId,
                'quantity' => $item->quantity,
            ];
        }

        return [
            'id' => $cart->id,
            'user_id' => $cart->userId,
            'items' => $items,
        ];
    }
}
