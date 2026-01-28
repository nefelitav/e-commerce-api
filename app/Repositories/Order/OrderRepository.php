<?php

namespace App\Repositories\Order;

use App\Dto\Order\Order;
use App\Dto\Order\UnpersistedOrder;
use App\Exceptions\OrderNotFoundException;
use App\Models\Order\OrderItemModel;
use App\Models\Order\OrderModel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class OrderRepository
{
    /**
     * @return array<Order>
     */
    public function getAll(): array
    {
        /** @var Collection<int, OrderModel> $orders */
        $orders = OrderModel::with('items')->get();

        return $orders->map(fn (OrderModel $model) => Order::fromModel($model))->all();
    }

    public function findById(int $id): ?Order
    {
        /** @var OrderModel|null $order */
        $order = OrderModel::with('items')->find($id);

        return $order ? Order::fromModel($order) : null;
    }

    public function persist(UnpersistedOrder $unpersistedOrder): Order
    {
        /** @var Order $created */
        $created = DB::transaction(function () use ($unpersistedOrder) {
            /** @var OrderModel $orderModel */
            $orderModel = OrderModel::create($unpersistedOrder->toArray());

            foreach ($unpersistedOrder->items as $item) {
                OrderItemModel::create($item->toArray($orderModel->id));
            }

            $orderModel->load('items');

            return Order::fromModel($orderModel);
        });

        return $created;
    }

    /**
     * @throws OrderNotFoundException
     */
    public function update(int $id, UnpersistedOrder $unpersistedOrder): Order
    {
        /** @var Order $updated */
        $updated = DB::transaction(function () use ($id, $unpersistedOrder) {
            /** @var OrderModel|null $orderModel */
            $orderModel = OrderModel::query()->where('id', $id)->first();

            if (!$orderModel) {
                throw new OrderNotFoundException($id);
            }

            $orderModel->update($unpersistedOrder->toArray());

            if (!empty($unpersistedOrder->items)) {
                OrderItemModel::query()->where('order_id', $orderModel->id)->delete();
                foreach ($unpersistedOrder->items as $item) {
                    OrderItemModel::create($item->toArray($orderModel->id));
                }
            }

            $orderModel->load('items');

            return Order::fromModel($orderModel);
        });

        return $updated;
    }

    /**
     * @throws OrderNotFoundException
     */
    public function delete(int $id): bool
    {
        /** @var OrderModel|null $orderModel */
        $orderModel = OrderModel::query()->where('id', $id)->first();

        if (!$orderModel) {
            throw new OrderNotFoundException($id);
        }

        OrderItemModel::query()->where('order_id', $orderModel->id)->delete();

        return $orderModel->delete();
    }
}

