<?php

namespace App\Dto\Order;

final readonly class UnpersistedOrderItem
{
    public function __construct(
        public int $productId,
        public int $quantity,
        public float $unitPrice,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(int $orderId): array
    {
        return [
            'order_id' => $orderId,
            'product_id' => $this->productId,
            'quantity' => $this->quantity,
            'unit_price' => $this->unitPrice,
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
            (float) $data['unit_price'],
        );
    }
}

