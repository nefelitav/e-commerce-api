<?php

namespace App\Http\Controllers\Category;

use App\Exceptions\BadRequestException;
use App\Exceptions\CategoryNotFoundException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Category\DeleteCategoryRequest;
use App\Http\Responses\ApiResponse;
use App\Http\Responses\Category\DeleteCategoryResponse;
use App\Services\Category\CategoryService;
use App\Transformers\CategoryTransformer;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Log\Logger;

final readonly class DeleteCategoryController extends Controller
{
    use ApiResponse;

    public function __construct(
        private CategoryService $service,
        private CategoryTransformer $transformer,
        private Logger $logger,
    ) {
    }

    public function delete(DeleteCategoryRequest $request): JsonResponse
    {
        $deleteCategoryResponse = $this->executeRequest($request);

        return self::success($deleteCategoryResponse, Response::HTTP_NO_CONTENT);
    }

    private function executeRequest(DeleteCategoryRequest $request): DeleteCategoryResponse
    {
        try {
            /** @var array<string, mixed> $validatedData */
            $validatedData = $request->validated();

           $this->service->deleteCategory($validatedData['id']);
        } catch (CategoryNotFoundException $e) {
            throw BadRequestException::fromException($e);
        }

        $this->logger->info("Category deleted successfully.", ["category_id" => $validatedData['id']]);

        return new DeleteCategoryResponse();
    }
}
