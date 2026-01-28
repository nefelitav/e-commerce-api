<?php

namespace App\Http\Controllers\Product;

use App\Dto\Product\UnpersistedProduct;
use App\Exceptions\BadRequestException;
use App\Exceptions\ProductAlreadyExistsException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Product\CreateProductRequest;
use App\Http\Responses\ApiResponse;
use App\Http\Responses\Product\CreateProductResponse;
use App\Services\Product\ProductService;
use App\Transformers\ProductTransformer;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Log\Logger;

final readonly class CreateProductController extends Controller
{
    use ApiResponse;

    public function __construct(
        private ProductService      $service,
        private ProductTransformer $transformer,
        private Logger              $logger,
    ) {
    }

    public function store(CreateProductRequest $request): JsonResponse
    {
        $createProductResponse = $this->executeRequest($request);

        return self::success($createProductResponse, Response::HTTP_CREATED);
    }

    private function executeRequest(CreateProductRequest $request): CreateProductResponse
    {
        try {
            /** @var array<string, mixed> $validatedData */
            $validatedData = $request->validated();
            $unpersistedProduct = UnpersistedProduct::fromArray($validatedData);

            $createdProduct = $this->service->createProduct($unpersistedProduct);
        } catch (ProductAlreadyExistsException $e) {
            throw new BadRequestException($e);
        }

        $createdProductData = $this->transformer->transform($createdProduct);
        $this->logger->info("New product created.", ["product" => $createdProductData]);

        return new CreateProductResponse($createdProductData);
    }
}
