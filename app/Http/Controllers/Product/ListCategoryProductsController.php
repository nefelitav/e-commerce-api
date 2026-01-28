<?php

namespace App\Http\Controllers\Product;

use App\Exceptions\UnprocessableEntityException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Product\ListCategoryProductsRequest;
use App\Http\Responses\ApiResponse;
use App\Http\Responses\Product\ListProductsResponse;
use App\Services\Product\ProductService;
use App\Transformers\ProductTransformer;
use Exception;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Log\Logger;

final readonly class ListCategoryProductsController extends Controller
{
    use ApiResponse;

    public function __construct(
        private ProductService      $service,
        private ProductTransformer $transformer,
        private Logger              $logger,
    ) {
    }

    public function index(ListCategoryProductsRequest $request): JsonResponse
    {
        $listCategoriesResponse = $this->executeRequest($request);

        return self::success($listCategoriesResponse, Response::HTTP_OK);
    }

    private function executeRequest(ListCategoryProductsRequest $request): ListProductsResponse
    {
        try {
            /** @var array<string, mixed> $validatedData */
            $validatedData = $request->validated();

            $products = $this->service->getProductsByCategoryId($validatedData['id']);
        } catch (Exception $e) {
            throw new UnprocessableEntityException($e);
        }

        $productsArray = [];
        foreach ($products as $product) {
            $productsArray[] = $this->transformer->transform($product);

        }
        $this->logger->info("Products found.", ["products" => $productsArray]);

        return new ListProductsResponse($productsArray);
    }
}
