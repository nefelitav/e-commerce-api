<?php

namespace App\Http\Controllers\Api\V1\Order;

use App\Dto\Order\UnpersistedOrder;
use App\Exceptions\BadRequestException;
use App\Exceptions\OrderNotFoundException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Order\UpdateOrderRequest;
use App\Http\Responses\ApiResponse;
use App\Http\Responses\Order\UpdateOrderResponse;
use App\Services\Order\OrderService;
use App\Transformers\OrderTransformer;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Log\Logger;

final readonly class UpdateOrderController extends Controller
{
    use ApiResponse;

    public function __construct(
        private OrderService $service,
        private OrderTransformer $transformer,
        private Logger $logger,
    ) {
    }

    public function update(UpdateOrderRequest $request): JsonResponse
    {
        $updateOrderResponse = $this->executeRequest($request);

        return self::success($updateOrderResponse, Response::HTTP_OK);
    }

    private function executeRequest(UpdateOrderRequest $request): UpdateOrderResponse
    {
        try {
            /** @var array<string, mixed> $validatedData */
            $validatedData = $request->validated();

            $existing = $this->service->getOrderById($validatedData['id']);
            if ($existing === null) {
                throw new BadRequestException();
            }

            $validatedData['user_id'] = $validatedData['user_id'] ?? $existing->userId;
            $unpersistedOrder = UnpersistedOrder::fromArray($validatedData);

            $updatedOrder = $this->service->updateOrder($validatedData['id'], $unpersistedOrder);
        } catch (OrderNotFoundException $e) {
            throw new BadRequestException($e);
        }

        $updatedOrderData = $this->transformer->transform($updatedOrder);
        $this->logger->info("Order updated successfully.", ["order" => $updatedOrderData]);

        return new UpdateOrderResponse($updatedOrderData);
    }
}

