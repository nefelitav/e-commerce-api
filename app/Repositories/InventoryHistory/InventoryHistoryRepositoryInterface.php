<?php

namespace App\Repositories\InventoryHistory;

use App\Dto\InventoryHistory\InventoryHistoryEntry;
use App\Dto\InventoryHistory\UnpersistedInventoryHistoryEntry;
use Illuminate\Pagination\LengthAwarePaginator;

interface InventoryHistoryRepositoryInterface
{
    /**
     * Oldest first (by id), paginated: a busy product can accumulate unbounded history.
     *
     * @return LengthAwarePaginator<int, InventoryHistoryEntry>
     */
    public function listByProductId(int $productId, int $page = 1, int $perPage = 100): LengthAwarePaginator;

    public function record(UnpersistedInventoryHistoryEntry $entry): InventoryHistoryEntry;
}
