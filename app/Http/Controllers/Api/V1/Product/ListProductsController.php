<?php

namespace App\Http\Controllers\Api\V1\Product;

use App\Exceptions\UnprocessableEntityException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Product\ListProductsRequest;
use App\Http\Responses\ApiResponse;
use App\Http\Responses\Product\ListProductsResponse;
use App\Services\Product\ProductService;
use App\Transformers\ProductTransformer;
use Exception;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Log\Logger;

final readonly class ListProductsController extends Controller
{
    use ApiResponse;

    public function __construct(
        private ProductService      $service,
        private ProductTransformer $transformer,
        private Logger              $logger,
    ) {
    }

    public function index(ListProductsRequest $request): JsonResponse
    {
        /** @var array<string, mixed> $validated */
        $validated = $request->validated();

        $listProductsResponse = $this->executeRequest(
            $validated['page'],
            $validated['per_page'],
            $validated['sort'],
            $validated['order'],
            $validated['filter'],
            $validated['include']
        );

        return self::success($listProductsResponse, Response::HTTP_OK);
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
    ): ListProductsResponse {
        try {
            $productsPaginator = $this->service->listProducts(
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

        $productsArray = [];
        foreach ($productsPaginator->items() as $product) {
            $productsArray[] = $this->transformer->transform($product);
        }
        $this->logger->info("Products found.", ["products" => $productsArray]);

        return new ListProductsResponse(
            $productsArray,
            [
                'current_page' => $productsPaginator->currentPage(),
                'per_page' => $productsPaginator->perPage(),
                'total' => $productsPaginator->total(),
                'last_page' => $productsPaginator->lastPage(),
            ]
        );
    }
}
