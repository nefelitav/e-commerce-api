<?php

namespace App\Http\Controllers\Api\V1\Order;

use App\CQRS\CommandBus;
use App\CQRS\Commands\Order\CreateOrderCommand;
use App\CQRS\Commands\Order\CreateOrderCommandItem;
use App\Dto\Order\Order;
use App\Enums\OrderStatus;
use App\Exceptions\BadRequestException;
use App\Exceptions\CouponNotFoundException;
use App\Exceptions\InsufficientStockException;
use App\Exceptions\InvalidCouponException;
use App\Exceptions\ProductNotFoundException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Order\CreateOrderRequest;
use App\Http\Responses\ApiResponse;
use App\Http\Responses\Order\CreateOrderResponse;
use App\Transformers\OrderTransformer;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Log\Logger;

final readonly class CreateOrderController extends Controller
{
    use ApiResponse;

    public function __construct(
        private CommandBus $commandBus,
        private OrderTransformer $transformer,
        private Logger $logger,
    ) {}

    public function store(CreateOrderRequest $request): JsonResponse
    {
        /** @var array<string, mixed> $validated */
        $validated = $request->validated();

        $userId = $request->user()->id ?? ($validated['user_id'] ?? null);
        if ($userId === null) {
            throw new BadRequestException('User is required to create an order');
        }

        /** @var array<int, array<string, mixed>> $rawItems */
        $rawItems = $validated['items'];

        $items = array_map(
            static fn (array $item) => new CreateOrderCommandItem(
                productId: (int) $item['product_id'],
                quantity: (int) $item['quantity'],
                unitPrice: (float) $item['unit_price'],
            ),
            $rawItems,
        );

        $command = new CreateOrderCommand(
            userId: (int) $userId,
            status: OrderStatus::from($validated['status']),
            totalPrice: (float) $validated['total_price'],
            items: $items,
            couponCode: isset($validated['coupon_code']) ? (string) $validated['coupon_code'] : null,
            idempotencyKey: $this->idempotencyKey($request),
        );

        try {
            /** @var Order $order */
            $order = $this->commandBus->dispatch($command);
        } catch (ProductNotFoundException|InsufficientStockException|CouponNotFoundException|InvalidCouponException $e) {
            throw new BadRequestException($e);
        }

        $orderData = $this->transformer->transform($order);
        $this->logger->info('New order created.', ['order' => $orderData]);

        return self::success(new CreateOrderResponse($orderData), Response::HTTP_CREATED);
    }

    /**
     * Optional `Idempotency-Key` header: a retried request with the same key
     * returns the original order instead of creating (and charging stock for) another.
     */
    private function idempotencyKey(\Illuminate\Http\Request $request): ?string
    {
        $key = $request->header('Idempotency-Key');

        if ($key === null || $key === '') {
            return null;
        }

        if (strlen($key) > 255) {
            throw new BadRequestException('Idempotency-Key must be at most 255 characters.');
        }

        return $key;
    }
}
