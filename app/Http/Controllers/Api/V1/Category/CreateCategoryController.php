<?php

namespace App\Http\Controllers\Api\V1\Category;

use App\Dto\Category\UnpersistedCategory;
use App\Exceptions\BadRequestException;
use App\Exceptions\CategoryAlreadyExistsException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Category\CreateCategoryRequest;
use App\Http\Responses\ApiResponse;
use App\Http\Responses\Category\CreateCategoryResponse;
use App\Services\Category\CategoryService;
use App\Transformers\CategoryTransformer;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Log\Logger;

final readonly class CreateCategoryController extends Controller
{
    use ApiResponse;

    public function __construct(
        private CategoryService $service,
        private CategoryTransformer $transformer,
        private Logger $logger,
    ) {
    }

    public function store(CreateCategoryRequest $request): JsonResponse
    {
        $createCategoryResponse = $this->executeRequest($request);

        return self::success($createCategoryResponse, Response::HTTP_CREATED);
    }

    private function executeRequest(CreateCategoryRequest $request): CreateCategoryResponse
    {
        try {
            /** @var array<string, mixed> $validatedData */
            $validatedData = $request->validated();
            $unpersistedCategory = UnpersistedCategory::fromArray($validatedData);

            $createdCategory = $this->service->createCategory($unpersistedCategory);
        } catch (CategoryAlreadyExistsException $e) {
            throw new BadRequestException($e->getMessage(), $e);
        }

        $createdCategoryData = $this->transformer->transform($createdCategory);
        $this->logger->info("New category created.", ["category" => $createdCategoryData]);

        return new CreateCategoryResponse($createdCategoryData);
    }
}
