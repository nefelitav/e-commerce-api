<?php

namespace App\Services\InventoryHistory;

use App\Dto\InventoryHistory\InventoryHistoryEntry;
use App\Repositories\InventoryHistory\InventoryHistoryRepository;

final readonly class InventoryHistoryService
{
    public function __construct(
        private InventoryHistoryRepository $repository,
    ) {
    }

    /**
     * @return array<InventoryHistoryEntry>
     */
    public function listByProductId(int $productId): array
    {
        return $this->repository->listByProductId($productId);
    }
}

