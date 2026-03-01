<?php

namespace App\Http\Controllers\Api\V1\Order;

use App\Exceptions\BadRequestException;
use App\Exceptions\UnprocessableEntityException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Order\GetOrderRequest;
use App\Http\Responses\ApiResponse;
use App\Http\Responses\Order\GetOrderResponse;
use App\Services\Order\OrderService;
use App\Transformers\OrderTransformer;
use Exception;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Log\Logger;

final readonly class GetOrderController extends Controller
{
    use ApiResponse;

    public function __construct(
        private OrderService $service,
        private OrderTransformer $transformer,
        private Logger $logger,
    ) {
    }

    public function show(GetOrderRequest $request): JsonResponse
    {
        $getOrderResponse = $this->executeRequest($request);

        return self::success($getOrderResponse, Response::HTTP_OK);
    }

    private function executeRequest(GetOrderRequest $request): GetOrderResponse
    {
        try {
            /** @var array<string, mixed> $validatedData */
            $validatedData = $request->validated();

            $order = $this->service->getOrderById($validatedData['id']);
        } catch (Exception $e) {
            throw new UnprocessableEntityException($e);
        }

        if ($order === null) {
            throw new BadRequestException();
        }

        $user = $request->user();
        if ($user !== null && !$user->isAdmin() && $order->userId !== $user->id) {
            throw new BadRequestException('You do not have access to this order.');
        }

        $foundOrder = $this->transformer->transform($order);
        $this->logger->info("Order found.", ["order" => $foundOrder]);

        return new GetOrderResponse($foundOrder);
    }
}

