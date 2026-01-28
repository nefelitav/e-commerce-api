<?php

namespace App\Dto\Cart;

use App\Models\Cart\CartModel;

final readonly class Cart
{
    /**
     * @param array<int, CartItem> $items
     */
    public function __construct(
        public int $id,
        public int $userId,
        public array $items = [],
    ) {}

    public static function fromModel(CartModel $cart): self
    {
        $items = [];
        if ($cart->relationLoaded('items')) {
            foreach ($cart->items as $item) {
                $items[] = CartItem::fromModel($item);
            }
        }

        return new self(
            $cart->id,
            $cart->user_id,
            $items,
        );
    }
}
