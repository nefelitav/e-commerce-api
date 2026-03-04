<?php

namespace App\Repositories\Order;

use App\Dto\Order\Order;
use App\Dto\Order\UnpersistedOrder;
use App\Exceptions\OrderNotFoundException;
use Illuminate\Pagination\LengthAwarePaginator;

interface OrderRepositoryInterface
{
    /**
     * @param array<string, mixed> $filters
     * @param array<string> $includes
     * @return LengthAwarePaginator<int, Order>
     */
    public function getAll(
        int $page = 1,
        int $perPage = 15,
        string $sort = 'id',
        string $order = 'asc',
        array $filters = [],
        array $includes = []
    ): LengthAwarePaginator;

    public function findById(int $id): ?Order;

    public function persist(UnpersistedOrder $unpersistedOrder): Order;

    /**
     * @throws OrderNotFoundException
     */
    public function update(int $id, UnpersistedOrder $unpersistedOrder): Order;

    /**
     * @throws OrderNotFoundException
     */
    public function delete(int $id): bool;
}

