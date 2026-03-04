<?php

namespace App\Services\Order;

use App\Dto\Order\Order;
use App\Dto\Order\UnpersistedOrder;
use App\Exceptions\InsufficientStockException;
use App\Exceptions\InvalidOrderStateException;
use App\Exceptions\OrderNotFoundException;
use App\Exceptions\ProductNotFoundException;
use Illuminate\Pagination\LengthAwarePaginator;

interface OrderServiceInterface
{
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
    ): LengthAwarePaginator;

    public function getOrderById(int $id): ?Order;

    /**
     * @throws ProductNotFoundException
     * @throws InsufficientStockException
     */
    public function createOrder(UnpersistedOrder $unpersistedOrder): Order;

    /**
     * @throws OrderNotFoundException
     * @throws InvalidOrderStateException
     */
    public function markOrderAsPaid(int $orderId, string $paymentReference): Order;

    /**
     * @throws OrderNotFoundException
     * @throws InvalidOrderStateException
     */
    public function updateOrder(int $id, UnpersistedOrder $unpersistedOrder, bool $asAdmin = false): Order;

    /**
     * @throws OrderNotFoundException
     */
    public function deleteOrder(int $id): bool;
}

