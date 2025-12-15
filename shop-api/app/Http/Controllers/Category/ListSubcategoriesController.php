<?php

namespace App\Http\Controllers\Category;

use App\Exceptions\UnprocessableEntityException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Category\GetCategoryRequest;
use App\Http\Requests\Category\ListSubcategoriesRequest;
use App\Http\Responses\ApiResponse;
use App\Http\Responses\Category\ListSubcategoriesResponse;
use App\Services\Category\CategoryService;
use App\Transformers\CategoryTransformer;
use Exception;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Log\Logger;

final readonly class ListSubcategoriesController extends Controller
{
    use ApiResponse;

    public function __construct(
        private CategoryService $service,
        private CategoryTransformer $transformer,
        private Logger $logger,
    ) {
    }

    public function index(ListSubcategoriesRequest $request): JsonResponse
    {
        $listSubcategoriesResponse = $this->executeRequest($request);

        return self::success($listSubcategoriesResponse, Response::HTTP_OK);
    }

    private function executeRequest(ListSubcategoriesRequest $request): ListSubcategoriesResponse
    {
        try {
            /** @var array<string, mixed> $validatedData */
            $validatedData = $request->validated();

            $subCategories = $this->service->listSubcategories($validatedData['id']);
        } catch (Exception $e) {
            throw UnprocessableEntityException::fromException($e);
        }

        $subCategoriesArray = [];
        foreach ($subCategories as $subCategory) {
            $subCategoriesArray[] = $this->transformer->transform($subCategory);

        }
        $this->logger->info("Subcategories found.", ["subcategories" => $subCategoriesArray]);

        return new ListSubcategoriesResponse($subCategoriesArray);
    }
}
