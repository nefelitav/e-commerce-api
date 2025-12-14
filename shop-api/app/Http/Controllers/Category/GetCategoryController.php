<?php

namespace App\Http\Controllers\Category;

use App\Exceptions\CategoryNotFoundException;
use App\Exceptions\UnprocessableEntityException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Category\GetCategoryRequest;
use App\Http\Responses\ApiResponse;
use App\Http\Responses\Category\GetCategoryResponse;
use App\Services\Category\CategoryService;
use App\Transformers\CategoryTransformer;
use Exception;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Log\Logger;

final readonly class GetCategoryController extends Controller
{
    use ApiResponse;

    public function __construct(
        private CategoryService $service,
        private CategoryTransformer $transformer,
        private Logger $logger,
    ) {
    }

    public function get(GetCategoryRequest $request): JsonResponse
    {
        $getCategoryResponse = $this->executeRequest($request);

        return self::success($getCategoryResponse, Response::HTTP_FOUND);
    }

    /**
     * @throws CategoryNotFoundException
     */
    private function executeRequest(GetCategoryRequest $request): GetCategoryResponse
    {
        try {
            /** @var array<string, mixed> $validatedData */
            $validatedData = $request->validated();

            $category = $this->service->getCategoryById($validatedData['id']);
        } catch (Exception $e) {
            throw UnprocessableEntityException::fromException($e);
        }

        if ($category === null) {
            throw new CategoryNotFoundException($validatedData['id']);
        }

        $foundCategory = $this->transformer->transform($category);
        $this->logger->info("Category found.", ["category" => $foundCategory]);

        return new GetCategoryResponse($this->transformer, $category);
    }
}
