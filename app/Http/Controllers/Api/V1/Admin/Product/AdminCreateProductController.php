<?php

namespace App\Http\Controllers\Api\V1\Admin\Product;

use App\CQRS\CommandBus;
use App\CQRS\Commands\Product\CreateProductCommand;
use App\Dto\Product\Product;
use App\Exceptions\BadRequestException;
use App\Exceptions\ProductAlreadyExistsException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Product\AdminCreateProductRequest;
use App\Http\Responses\ApiResponse;
use App\Http\Responses\Product\CreateProductResponse;
use App\Transformers\ProductTransformer;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Log\Logger;

final readonly class AdminCreateProductController extends Controller
{
    use ApiResponse;

    public function __construct(
        private CommandBus        $commandBus,
        private ProductTransformer $transformer,
        private Logger             $logger,
    ) {}

    public function store(AdminCreateProductRequest $request): JsonResponse
    {
        /** @var array<string, mixed> $validated */
        $validated = $request->validated();

        $command = new CreateProductCommand(
            name:        $validated['name'],
            description: $validated['description'] ?? null,
            price:       (float) $validated['price'],
            quantity:    (int)   $validated['quantity'],
            categoryId:  (int)   $validated['category_id'],
        );

        try {
            /** @var Product $product */
            $product = $this->commandBus->dispatch($command);
        } catch (ProductAlreadyExistsException $e) {
            throw new BadRequestException($e);
        }

        $productData = $this->transformer->transform($product);
        $this->logger->info('Admin created product via command bus.', ['product' => $productData]);

        return self::success(new CreateProductResponse($productData), Response::HTTP_CREATED);
    }
}

