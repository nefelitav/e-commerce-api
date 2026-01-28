<?php

namespace App\Http\Controllers\Product;

use App\Dto\Product\UnpersistedProduct;
use App\Exceptions\BadRequestException;
use App\Exceptions\ProductNotFoundException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Product\UpdateProductRequest;
use App\Http\Responses\ApiResponse;
use App\Http\Responses\Product\UpdateProductResponse;
use App\Services\Product\ProductService;
use App\Transformers\ProductTransformer;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Log\Logger;

final readonly class UpdateProductController extends Controller
{
    use ApiResponse;

    public function __construct(
        private ProductService      $service,
        private ProductTransformer $transformer,
        private Logger              $logger,
    ) {
    }

    public function update(UpdateProductRequest $request): JsonResponse
    {
        $createProductResponse = $this->executeRequest($request);

        return self::success($createProductResponse, Response::HTTP_OK);
    }

    private function executeRequest(UpdateProductRequest $request): UpdateProductResponse
    {
        try {
            /** @var array<string, mixed> $validatedData */
            $validatedData = $request->validated();
            $unpersistedProduct = UnpersistedProduct::fromArray($validatedData);

            $updatedProduct = $this->service->updateProduct($validatedData['id'], $unpersistedProduct);
        } catch (ProductNotFoundException $e) {
            throw new BadRequestException($e);
        }

        $updatedProductData = $this->transformer->transform($updatedProduct);
        $this->logger->info("Product updated successfully.", ["product" => $updatedProductData]);

        return new UpdateProductResponse($updatedProductData);
    }
}
