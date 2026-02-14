<?php

namespace App\Http\Controllers\Api\V1\Order;

use App\Dto\Order\UnpersistedOrder;
use App\Exceptions\BadRequestException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Order\CreateOrderRequest;
use App\Http\Responses\ApiResponse;
use App\Http\Responses\Order\CreateOrderResponse;
use App\Services\Order\OrderService;
use App\Transformers\OrderTransformer;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Log\Logger;

final readonly class CreateOrderController extends Controller
{
    use ApiResponse;

    public function __construct(
        private OrderService $service,
        private OrderTransformer $transformer,
        private Logger $logger,
    ) {
    }

    public function store(CreateOrderRequest $request): JsonResponse
    {
        $createOrderResponse = $this->executeRequest($request);

        return self::success($createOrderResponse, Response::HTTP_CREATED);
    }

    private function executeRequest(CreateOrderRequest $request): CreateOrderResponse
    {
        /** @var array<string, mixed> $validatedData */
        $validatedData = $request->validated();

        $userId = $request->user()->id ?? ($validatedData['user_id'] ?? null);
        if ($userId === null) {
            throw new BadRequestException('User is required to create an order');
        }

        $validatedData['user_id'] = $userId;
        $unpersistedOrder = UnpersistedOrder::fromArray($validatedData);

        $createdOrder = $this->service->createOrder($unpersistedOrder);

        $createdOrderData = $this->transformer->transform($createdOrder);
        $this->logger->info("New order created.", ["order" => $createdOrderData]);

        return new CreateOrderResponse($createdOrderData);
    }
}

