<?php

namespace App\Services\Order;

use App\Dto\Order\Order;
use App\Dto\Order\UnpersistedOrder;
use App\Exceptions\OrderNotFoundException;
use App\Repositories\Order\OrderRepository;
use Illuminate\Pagination\LengthAwarePaginator;

final readonly class OrderService
{
    public function __construct(
        private OrderRepository $repository,
    ) {
    }

    /**
     * @param array<string, mixed> $filters
     * @param array<string> $includes
     * @return LengthAwarePaginator<int, Order>
     */
    public function listOrders(
        int $page = 1,
        int $perPage = 15,
        string $sort = 'id',
        string $order = 'asc',
        array $filters = [],
        array $includes = []
    ): LengthAwarePaginator {
        return $this->repository->getAll($page, $perPage, $sort, $order, $filters, $includes);
    }

    public function getOrderById(int $id): ?Order
    {
        return $this->repository->findById($id);
    }

    public function createOrder(UnpersistedOrder $unpersistedOrder): Order
    {
        return $this->repository->persist($unpersistedOrder);
    }

    /**
     * @throws OrderNotFoundException
     */
    public function updateOrder(int $id, UnpersistedOrder $unpersistedOrder): Order
    {
        return $this->repository->update($id, $unpersistedOrder);
    }

    /**
     * @throws OrderNotFoundException
     */
    public function deleteOrder(int $id): bool
    {
        return $this->repository->delete($id);
    }
}

