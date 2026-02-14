<?php

namespace App\Http\Controllers\Api\V1\Product;

use App\Exceptions\BadRequestException;
use App\Exceptions\ProductNotFoundException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Product\DeleteProductRequest;
use App\Http\Responses\ApiResponse;
use App\Http\Responses\Product\DeleteProductResponse;
use App\Services\Product\ProductService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Log\Logger;

final readonly class DeleteProductController extends Controller
{
    use ApiResponse;

    public function __construct(
        private ProductService $service,
        private Logger         $logger,
    ) {
    }

    public function destroy(DeleteProductRequest $request): JsonResponse
    {
        $deleteProductResponse = $this->executeRequest($request);

        return self::success($deleteProductResponse, Response::HTTP_NO_CONTENT);
    }

    private function executeRequest(DeleteProductRequest $request): DeleteProductResponse
    {
        try {
            /** @var array<string, mixed> $validatedData */
            $validatedData = $request->validated();

           $this->service->deleteProduct($validatedData['id']);
        } catch (ProductNotFoundException $e) {
            throw new BadRequestException($e);
        }

        $this->logger->info("Product deleted successfully.", ["product_id" => $validatedData['id']]);

        return new DeleteProductResponse();
    }
}
