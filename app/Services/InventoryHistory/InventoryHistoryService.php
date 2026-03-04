<?php

namespace App\Services\InventoryHistory;

use App\Dto\InventoryHistory\InventoryHistoryEntry;
use App\Repositories\InventoryHistory\InventoryHistoryRepositoryInterface;

final readonly class InventoryHistoryService
{
    public function __construct(
        private InventoryHistoryRepositoryInterface $repository,
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

