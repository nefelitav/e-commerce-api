<?php

namespace App\Services\InventoryHistory;

use App\Dto\InventoryHistory\InventoryHistoryEntry;
use App\Repositories\InventoryHistory\InventoryHistoryRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;

final readonly class InventoryHistoryService
{
    public function __construct(
        private InventoryHistoryRepositoryInterface $repository,
    ) {}

    /**
     * @return LengthAwarePaginator<int, InventoryHistoryEntry>
     */
    public function listByProductId(int $productId, int $page = 1, int $perPage = 100): LengthAwarePaginator
    {
        return $this->repository->listByProductId($productId, $page, $perPage);
    }
}
