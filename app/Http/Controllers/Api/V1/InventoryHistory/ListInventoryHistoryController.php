<?php

namespace App\Http\Controllers\Api\V1\InventoryHistory;

use App\Exceptions\UnprocessableEntityException;
use App\Http\Controllers\Controller;
use App\Http\Requests\InventoryHistory\ListInventoryHistoryRequest;
use App\Http\Responses\ApiResponse;
use App\Http\Responses\InventoryHistory\ListInventoryHistoryResponse;
use App\Services\InventoryHistory\InventoryHistoryService;
use App\Transformers\InventoryHistoryTransformer;
use Exception;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Log\Logger;

final readonly class ListInventoryHistoryController extends Controller
{
    use ApiResponse;

    public function __construct(
        private InventoryHistoryService $service,
        private InventoryHistoryTransformer $transformer,
        private Logger $logger,
    ) {}

    public function index(ListInventoryHistoryRequest $request): JsonResponse
    {
        $listResponse = $this->executeRequest($request);

        return self::success($listResponse, Response::HTTP_OK);
    }

    private function executeRequest(ListInventoryHistoryRequest $request): ListInventoryHistoryResponse
    {
        try {
            /** @var array<string, mixed> $validatedData */
            $validatedData = $request->validated();

            $paginator = $this->service->listByProductId(
                (int) $validatedData['id'],
                (int) ($validatedData['page'] ?? 1),
                (int) ($validatedData['per_page'] ?? 100),
            );
        } catch (Exception $e) {
            throw new UnprocessableEntityException($e);
        }

        $entriesArray = [];
        foreach ($paginator->items() as $entry) {
            $entriesArray[] = $this->transformer->transform($entry);
        }

        $this->logger->info('Inventory history found.', [
            'product_id' => $validatedData['id'],
            'entries' => count($entriesArray),
        ]);

        return new ListInventoryHistoryResponse($entriesArray, [
            'current_page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'last_page' => $paginator->lastPage(),
        ]);
    }
}
