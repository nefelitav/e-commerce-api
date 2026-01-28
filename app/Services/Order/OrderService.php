<?php

namespace App\Services\Order;

use App\Dto\Order\Order;
use App\Dto\Order\UnpersistedOrder;
use App\Exceptions\OrderNotFoundException;
use App\Repositories\Order\OrderRepository;

final readonly class OrderService
{
    public function __construct(
        private OrderRepository $repository,
    ) {
    }

    /**
     * @return array<Order>
     */
    public function listOrders(): array
    {
        return $this->repository->getAll();
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

