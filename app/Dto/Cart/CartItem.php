<?php

namespace App\Dto\Cart;

use App\Models\Cart\CartItemModel;

final readonly class CartItem
{
    public function __construct(
        public int $id,
        public int $cartId,
        public int $productId,
        public int $quantity,
    ) {}

    public static function fromModel(CartItemModel $item): self
    {
        return new self(
            $item->id,
            $item->cart_id,
            $item->product_id,
            $item->quantity,
        );
    }
}
