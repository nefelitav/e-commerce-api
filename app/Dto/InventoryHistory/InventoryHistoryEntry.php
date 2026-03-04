<?php

namespace App\Dto\InventoryHistory;

use App\Enums\InventoryChangeType;
use App\Models\InventoryHistory\InventoryHistoryModel;

final readonly class InventoryHistoryEntry
{
    public function __construct(
        public int $id,
        public int $productId,
        public InventoryChangeType $changeType,
        public int $quantityChanged,
        public int $previousQuantity,
        public int $newQuantity,
    ) {}

    public static function fromModel(InventoryHistoryModel $model): self
    {
        return new self(
            $model->id,
            $model->product_id,
            InventoryChangeType::from($model->change_type),
            $model->quantity_changed,
            $model->previous_quantity,
            $model->new_quantity,
        );
    }
}

