<?php

namespace App\Http\Controllers\Category;

use App\Dto\Category\UnpersistedCategory;
use App\Exceptions\BadRequestException;
use App\Exceptions\CategoryNotFoundException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Category\UpdateCategoryRequest;
use App\Http\Responses\ApiResponse;
use App\Http\Responses\Category\UpdateCategoryResponse;
use App\Services\Category\CategoryService;
use App\Transformers\CategoryTransformer;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Log\Logger;

final readonly class UpdateCategoryController extends Controller
{
    use ApiResponse;

    public function __construct(
        private CategoryService $service,
        private CategoryTransformer $transformer,
        private Logger $logger,
    ) {
    }

    public function update(UpdateCategoryRequest $request): JsonResponse
    {
        $createCategoryResponse = $this->executeRequest($request);

        return self::success($createCategoryResponse, Response::HTTP_OK);
    }

    private function executeRequest(UpdateCategoryRequest $request): UpdateCategoryResponse
    {
        try {
            /** @var array<string, mixed> $validatedData */
            $validatedData = $request->validated();
            $unpersistedCategory = UnpersistedCategory::fromArray($validatedData);

            $updatedCategory = $this->service->updateCategory($validatedData['id'], $unpersistedCategory);
        } catch (CategoryNotFoundException $e) {
            throw BadRequestException::fromException($e);
        }

        $updatedCategoryData = $this->transformer->transform($updatedCategory);
        $this->logger->info("Category updated successfully.", ["category" => $updatedCategoryData]);

        return new UpdateCategoryResponse($updatedCategoryData);
    }
}
