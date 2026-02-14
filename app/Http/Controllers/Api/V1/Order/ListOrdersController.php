<?php

namespace App\Http\Controllers\Api\V1\Order;

use App\Exceptions\UnprocessableEntityException;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Http\Responses\Order\ListOrdersResponse;
use App\Services\Order\OrderService;
use App\Transformers\OrderTransformer;
use Exception;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Log\Logger;

final readonly class ListOrdersController extends Controller
{
    use ApiResponse;

    public function __construct(
        private OrderService $service,
        private OrderTransformer $transformer,
        private Logger $logger,
    ) {
    }

    public function index(): JsonResponse
    {
        $listOrdersResponse = $this->executeRequest();

        return self::success($listOrdersResponse, Response::HTTP_OK);
    }

    private function executeRequest(): ListOrdersResponse
    {
        try {
            $orders = $this->service->listOrders();
        } catch (Exception $e) {
            throw new UnprocessableEntityException($e);
        }

        $ordersArray = [];
        foreach ($orders as $order) {
            $ordersArray[] = $this->transformer->transform($order);
        }

        $this->logger->info("Orders found.", ["orders" => $ordersArray]);

        return new ListOrdersResponse($ordersArray);
    }
}

