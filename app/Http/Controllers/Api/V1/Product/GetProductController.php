<?php

namespace App\Http\Controllers\Api\V1\Product;

use App\Exceptions\BadRequestException;
use App\Exceptions\UnprocessableEntityException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Product\GetProductRequest;
use App\Http\Responses\ApiResponse;
use App\Http\Responses\Product\GetProductResponse;
use App\Services\Product\ProductService;
use App\Transformers\ProductTransformer;
use Exception;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Log\Logger;

final readonly class GetProductController extends Controller
{
    use ApiResponse;

    public function __construct(
        private ProductService      $service,
        private ProductTransformer $transformer,
        private Logger              $logger,
    ) {
    }

    public function show(GetProductRequest $request): JsonResponse
    {
        $getProductResponse = $this->executeRequest($request);

        return self::success($getProductResponse, Response::HTTP_OK);
    }

    private function executeRequest(GetProductRequest $request): GetProductResponse
    {
        try {
            /** @var array<string, mixed> $validatedData */
            $validatedData = $request->validated();

            $product = $this->service->getProductById($validatedData['id']);
        } catch (Exception $e) {
            throw new UnprocessableEntityException($e);
        }

        if ($product === null) {
            throw new BadRequestException();
        }

        $foundProduct = $this->transformer->transform($product);
        $this->logger->info("Product found.", ["product" => $foundProduct]);

        return new GetProductResponse($foundProduct);
    }
}
