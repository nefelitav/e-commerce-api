<?php

namespace App\Dto\InventoryHistory;

use App\Enums\InventoryChangeType;

final readonly class UnpersistedInventoryHistoryEntry
{
    public function __construct(
        public int $productId,
        public InventoryChangeType $changeType,
        public int $quantityChanged,
        public int $previousQuantity,
        public int $newQuantity,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'product_id' => $this->productId,
            'change_type' => $this->changeType->value,
            'quantity_changed' => $this->quantityChanged,
            'previous_quantity' => $this->previousQuantity,
            'new_quantity' => $this->newQuantity,
        ];
    }
}

