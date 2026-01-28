<?php

namespace App\Dto\Cart;

final readonly class UnpersistedCartItem
{
    public function __construct(
        public int $productId,
        public int $quantity,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(int $cartId): array
    {
        return [
            'cart_id' => $cartId,
            'product_id' => $this->productId,
            'quantity' => $this->quantity,
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            $data['product_id'],
            $data['quantity'],
        );
    }
}
