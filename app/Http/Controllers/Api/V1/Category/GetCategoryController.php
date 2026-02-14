<?php

namespace App\Http\Controllers\Api\V1\Category;

use App\Exceptions\BadRequestException;
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

    public function show(GetCategoryRequest $request): JsonResponse
    {
        $getCategoryResponse = $this->executeRequest($request);

        return self::success($getCategoryResponse, Response::HTTP_OK);
    }

    private function executeRequest(GetCategoryRequest $request): GetCategoryResponse
    {
        try {
            /** @var array<string, mixed> $validatedData */
            $validatedData = $request->validated();

            $category = $this->service->getCategoryById($validatedData['id']);
        } catch (Exception $e) {
            throw new UnprocessableEntityException($e);
        }

        if ($category === null) {
            throw new BadRequestException();
        }

        $foundCategory = $this->transformer->transform($category);
        $this->logger->info("Category found.", ["category" => $foundCategory]);

        return new GetCategoryResponse($foundCategory);
    }
}
