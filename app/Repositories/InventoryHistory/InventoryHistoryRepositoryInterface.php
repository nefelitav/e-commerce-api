<?php

namespace App\Repositories\InventoryHistory;

use App\Dto\InventoryHistory\InventoryHistoryEntry;
use App\Dto\InventoryHistory\UnpersistedInventoryHistoryEntry;

interface InventoryHistoryRepositoryInterface
{
    /**
     * @return array<InventoryHistoryEntry>
     */
    public function listByProductId(int $productId): array;

    public function record(UnpersistedInventoryHistoryEntry $entry): InventoryHistoryEntry;
}

