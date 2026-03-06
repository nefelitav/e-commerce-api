<?php

namespace App\Http\Controllers\Api\V1\ReturnRequest;

use App\Exceptions\UnprocessableEntityException;
use App\Http\Controllers\Controller;
use App\Http\Requests\ReturnRequest\ListReturnRequestsRequest;
use App\Http\Responses\ApiResponse;
use App\Http\Responses\ReturnRequest\ListReturnRequestsResponse;
use App\Services\ReturnRequest\ReturnRequestService;
use App\Transformers\ReturnRequestTransformer;
use Exception;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Log\Logger;

final readonly class ListReturnRequestsController extends Controller
{
    use ApiResponse;

    public function __construct(
        private ReturnRequestService $service,
        private ReturnRequestTransformer $transformer,
        private Logger $logger,
    ) {}

    public function index(ListReturnRequestsRequest $request): JsonResponse
    {
        /** @var array<string, mixed> $validated */
        $validated = $request->validated();

        $user = $request->user();
        if ($user !== null && ! $user->isAdmin()) {
            $validated['filter']['user_id'] = $user->id;
        }

        try {
            $paginator = $this->service->listReturnRequests(
                $validated['page'],
                $validated['per_page'],
                $validated['sort'],
                $validated['order'],
                $validated['filter'],
            );
        } catch (Exception $e) {
            throw new UnprocessableEntityException($e);
        }

        $returnRequestsArray = [];
        foreach ($paginator->items() as $returnRequest) {
            $returnRequestsArray[] = $this->transformer->transform($returnRequest);
        }

        $this->logger->info('Return requests found.', ['count' => count($returnRequestsArray)]);

        return self::success(
            new ListReturnRequestsResponse(
                $returnRequestsArray,
                [
                    'current_page' => $paginator->currentPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'last_page' => $paginator->lastPage(),
                ],
            ),
            Response::HTTP_OK,
        );
    }
}
