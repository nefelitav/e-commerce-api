<?php

namespace App\Repositories\InventoryHistory;

use App\Dto\InventoryHistory\InventoryHistoryEntry;
use App\Dto\InventoryHistory\UnpersistedInventoryHistoryEntry;
use App\Models\InventoryHistory\InventoryHistoryModel;
use Illuminate\Pagination\LengthAwarePaginator;

class InventoryHistoryRepository implements InventoryHistoryRepositoryInterface
{
    /**
     * @return LengthAwarePaginator<int, InventoryHistoryEntry>
     */
    public function listByProductId(int $productId, int $page = 1, int $perPage = 100): LengthAwarePaginator
    {
        $paginator = InventoryHistoryModel::query()
            ->where('product_id', $productId)
            ->orderBy('id')
            ->paginate($perPage, ['*'], 'page', $page);

        $items = $paginator->getCollection()->map(fn (InventoryHistoryModel $m) => InventoryHistoryEntry::fromModel($m));

        return new LengthAwarePaginator(
            $items,
            $paginator->total(),
            $paginator->perPage(),
            $paginator->currentPage(),
            ['path' => LengthAwarePaginator::resolveCurrentPath()],
        );
    }

    public function record(UnpersistedInventoryHistoryEntry $entry): InventoryHistoryEntry
    {
        /** @var InventoryHistoryModel $model */
        $model = InventoryHistoryModel::create($entry->toArray());

        return InventoryHistoryEntry::fromModel($model);
    }
}
