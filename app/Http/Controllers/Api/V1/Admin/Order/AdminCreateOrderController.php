<?php

namespace App\Http\Controllers\Api\V1\Admin\Order;

use App\CQRS\CommandBus;
use App\CQRS\Commands\Order\CreateOrderCommand;
use App\CQRS\Commands\Order\CreateOrderCommandItem;
use App\Dto\Order\Order;
use App\Enums\OrderStatus;
use App\Exceptions\BadRequestException;
use App\Exceptions\InsufficientStockException;
use App\Exceptions\ProductNotFoundException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Order\AdminCreateOrderRequest;
use App\Http\Responses\ApiResponse;
use App\Http\Responses\Order\CreateOrderResponse;
use App\Transformers\OrderTransformer;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Log\Logger;

final readonly class AdminCreateOrderController extends Controller
{
    use ApiResponse;

    public function __construct(
        private CommandBus     $commandBus,
        private OrderTransformer $transformer,
        private Logger           $logger,
    ) {}

    public function store(AdminCreateOrderRequest $request): JsonResponse
    {
        /** @var array<string, mixed> $validated */
        $validated = $request->validated();

        /** @var array<int, array<string, mixed>> $rawItems */
        $rawItems = $validated['items'];

        $items = array_map(
            static fn(array $item) => new CreateOrderCommandItem(
                productId: (int)   $item['product_id'],
                quantity:  (int)   $item['quantity'],
                unitPrice: (float) $item['unit_price'],
            ),
            $rawItems,
        );

        $command = new CreateOrderCommand(
            userId:     (int)   $validated['user_id'],
            status:     OrderStatus::from($validated['status']),
            totalPrice: (float) $validated['total_price'],
            items:      $items,
        );

        try {
            /** @var Order $order */
            $order = $this->commandBus->dispatch($command);
        } catch (ProductNotFoundException | InsufficientStockException $e) {
            throw new BadRequestException($e);
        }

        $orderData = $this->transformer->transform($order);
        $this->logger->info('Admin created order via command bus.', ['order' => $orderData]);

        return self::success(new CreateOrderResponse($orderData), Response::HTTP_CREATED);
    }
}

