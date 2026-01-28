<?php

namespace App\Transformers;

use App\Dto\InventoryHistory\InventoryHistoryEntry;

final readonly class InventoryHistoryTransformer
{
    /**
     * @return array<string, mixed>
     */
    public function transform(InventoryHistoryEntry $entry): array
    {
        return [
            'id' => $entry->id,
            'product_id' => $entry->productId,
            'change_type' => $entry->changeType,
            'quantity_changed' => $entry->quantityChanged,
            'previous_quantity' => $entry->previousQuantity,
            'new_quantity' => $entry->newQuantity,
            'created_at' => $entry->createdAt?->toISOString(),
        ];
    }
}

