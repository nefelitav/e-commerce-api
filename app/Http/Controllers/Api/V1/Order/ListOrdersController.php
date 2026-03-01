<?php

namespace App\Http\Controllers\Api\V1\Order;

use App\Exceptions\UnprocessableEntityException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Order\ListOrdersRequest;
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

    public function index(ListOrdersRequest $request): JsonResponse
    {
        /** @var array<string, mixed> $validated */
        $validated = $request->validated();

        // Non-admin users may only see their own orders.
        $user = $request->user();
        if ($user !== null && !$user->isAdmin()) {
            $validated['filter']['user_id'] = $user->id;
        }

        $listOrdersResponse = $this->executeRequest(
            $validated['page'],
            $validated['per_page'],
            $validated['sort'],
            $validated['order'],
            $validated['filter'],
            $validated['include']
        );

        return self::success($listOrdersResponse, Response::HTTP_OK);
    }

    /**
     * @param array<string, mixed> $filters
     * @param array<string> $includes
     */
    private function executeRequest(
        int $page,
        int $perPage,
        string $sort,
        string $order,
        array $filters,
        array $includes
    ): ListOrdersResponse {
        try {
            $ordersPaginator = $this->service->listOrders(
                $page,
                $perPage,
                $sort,
                $order,
                $filters,
                $includes
            );
        } catch (Exception $e) {
            throw new UnprocessableEntityException($e);
        }

        $ordersArray = [];
        foreach ($ordersPaginator->items() as $orderItem) {
            $ordersArray[] = $this->transformer->transform($orderItem);
        }

        $this->logger->info("Orders found.", ["orders" => $ordersArray]);

        return new ListOrdersResponse(
            $ordersArray,
            [
                'current_page' => $ordersPaginator->currentPage(),
                'per_page' => $ordersPaginator->perPage(),
                'total' => $ordersPaginator->total(),
                'last_page' => $ordersPaginator->lastPage(),
            ]
        );
    }
}

