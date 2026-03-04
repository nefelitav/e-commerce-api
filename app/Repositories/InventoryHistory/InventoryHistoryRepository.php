<?php

namespace App\Repositories\InventoryHistory;

use App\Dto\InventoryHistory\InventoryHistoryEntry;
use App\Dto\InventoryHistory\UnpersistedInventoryHistoryEntry;
use App\Models\InventoryHistory\InventoryHistoryModel;
use Illuminate\Database\Eloquent\Collection;

class InventoryHistoryRepository implements InventoryHistoryRepositoryInterface
{
    /**
     * @return array<InventoryHistoryEntry>
     */
    public function listByProductId(int $productId): array
    {
        /** @var Collection<int, InventoryHistoryModel> $entries */
        $entries = InventoryHistoryModel::query()
            ->where('product_id', $productId)
            ->get();

        return $entries->map(fn (InventoryHistoryModel $m) => InventoryHistoryEntry::fromModel($m))->all();
    }

    public function record(UnpersistedInventoryHistoryEntry $entry): InventoryHistoryEntry
    {
        /** @var InventoryHistoryModel $model */
        $model = InventoryHistoryModel::create($entry->toArray());

        return InventoryHistoryEntry::fromModel($model);
    }
}

