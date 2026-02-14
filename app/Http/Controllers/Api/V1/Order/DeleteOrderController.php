<?php

namespace App\Http\Controllers\Api\V1\Order;

use App\Exceptions\BadRequestException;
use App\Exceptions\OrderNotFoundException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Order\DeleteOrderRequest;
use App\Http\Responses\ApiResponse;
use App\Http\Responses\Order\DeleteOrderResponse;
use App\Services\Order\OrderService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Log\Logger;

final readonly class DeleteOrderController extends Controller
{
    use ApiResponse;

    public function __construct(
        private OrderService $service,
        private Logger $logger,
    ) {
    }

    public function destroy(DeleteOrderRequest $request): JsonResponse
    {
        $deleteOrderResponse = $this->executeRequest($request);

        return self::success($deleteOrderResponse, Response::HTTP_NO_CONTENT);
    }

    private function executeRequest(DeleteOrderRequest $request): DeleteOrderResponse
    {
        try {
            /** @var array<string, mixed> $validatedData */
            $validatedData = $request->validated();

            $this->service->deleteOrder($validatedData['id']);
        } catch (OrderNotFoundException $e) {
            throw new BadRequestException($e);
        }

        $this->logger->info("Order deleted successfully.", ["order_id" => $validatedData['id']]);

        return new DeleteOrderResponse();
    }
}

