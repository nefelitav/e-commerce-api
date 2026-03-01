<?php

namespace App\CQRS\Handlers\Order;

use App\CQRS\Commands\CommandInterface;
use App\CQRS\Commands\Order\CreateOrderCommand;
use App\CQRS\Commands\Order\CreateOrderCommandItem;
use App\CQRS\Handlers\CommandHandlerInterface;
use App\Dto\Order\Order;
use App\Dto\Order\UnpersistedOrder;
use App\Dto\Order\UnpersistedOrderItem;
use App\Exceptions\InsufficientStockException;
use App\Exceptions\ProductNotFoundException;
use App\Services\Order\OrderServiceInterface;

/**
 * @implements CommandHandlerInterface<CreateOrderCommand, Order>
 */
readonly class CreateOrderCommandHandler implements CommandHandlerInterface
{
    public function __construct(
        private OrderServiceInterface $orderService,
    ) {}

    /**
     * @param CreateOrderCommand $command
     * @throws ProductNotFoundException
     * @throws InsufficientStockException
     */
    public function handle(CommandInterface $command): Order
    {
        $items = array_map(
            static fn(CreateOrderCommandItem $item) => new UnpersistedOrderItem(
                productId: $item->productId,
                quantity:  $item->quantity,
                unitPrice: $item->unitPrice,
            ),
            $command->items,
        );

        $unpersistedOrder = new UnpersistedOrder(
            userId:     $command->userId,
            status:     $command->status,
            totalPrice: $command->totalPrice,
            items:      $items,
        );

        return $this->orderService->createOrder($unpersistedOrder);
    }
}



