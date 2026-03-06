<?php

namespace App\Http\Controllers\Api\V1\Product;

use App\Exceptions\UnprocessableEntityException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Product\SearchProductsRequest;
use App\Http\Responses\ApiResponse;
use App\Http\Responses\Product\SearchProductsResponse;
use App\Services\Product\ProductService;
use App\Transformers\ProductTransformer;
use Exception;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Log\Logger;

final readonly class SearchProductsController extends Controller
{
    use ApiResponse;

    public function __construct(
        private ProductService $service,
        private ProductTransformer $transformer,
        private Logger $logger,
    ) {}

    public function search(SearchProductsRequest $request): JsonResponse
    {
        /** @var array<string, mixed> $validated */
        $validated = $request->validated();

        try {
            $productsPaginator = $this->service->listProducts(
                $validated['page'],
                $validated['per_page'],
                $validated['sort'],
                $validated['order'],
                $validated['filter'],
            );
        } catch (Exception $e) {
            throw new UnprocessableEntityException($e);
        }

        $productsArray = [];
        foreach ($productsPaginator->items() as $product) {
            $productsArray[] = $this->transformer->transform($product);
        }

        $this->logger->info('Product search completed.', [
            'query' => $validated['q'],
            'results' => count($productsArray),
        ]);

        return self::success(
            new SearchProductsResponse(
                $productsArray,
                [
                    'current_page' => $productsPaginator->currentPage(),
                    'per_page' => $productsPaginator->perPage(),
                    'total' => $productsPaginator->total(),
                    'last_page' => $productsPaginator->lastPage(),
                ],
            ),
            Response::HTTP_OK,
        );
    }
}
