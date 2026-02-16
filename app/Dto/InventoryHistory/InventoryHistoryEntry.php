<?php

namespace App\Dto\InventoryHistory;

use App\Models\InventoryHistory\InventoryHistoryModel;
use Carbon\Carbon;

final readonly class InventoryHistoryEntry
{
    public function __construct(
        public int $id,
        public int $productId,
        public string $changeType,
        public int $quantityChanged,
        public int $previousQuantity,
        public int $newQuantity,
    ) {}

    public static function fromModel(InventoryHistoryModel $model): self
    {
        return new self(
            $model->id,
            $model->product_id,
            $model->change_type,
            $model->quantity_changed,
            $model->previous_quantity,
            $model->new_quantity,
        );
    }
}

