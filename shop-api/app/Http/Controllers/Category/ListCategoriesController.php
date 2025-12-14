<?php

namespace App\Http\Controllers\Category;

use App\Exceptions\UnprocessableEntityException;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Http\Responses\Category\ListCategoriesResponse;
use App\Services\Category\CategoryService;
use App\Transformers\CategoryTransformer;
use Exception;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Log\Logger;

final readonly class ListCategoriesController extends Controller
{
    use ApiResponse;

    public function __construct(
        private CategoryService $service,
        private CategoryTransformer $transformer,
        private Logger $logger,
    ) {
    }

    public function list(): JsonResponse
    {
        $listCategoriesResponse = $this->executeRequest();

        return self::success($listCategoriesResponse, Response::HTTP_OK);
    }

    private function executeRequest(): ListCategoriesResponse
    {
        try {
            $categories = $this->service->listCategories();
        } catch (Exception $e) {
            throw UnprocessableEntityException::fromException($e);
        }

        $categoriesArray = [];
        foreach ($categories as $category) {
            $categoriesArray[] = $this->transformer->transform($category);

        }
        $this->logger->info("Categories found.", ["categories" => $categoriesArray]);

        return new ListCategoriesResponse($this->transformer, $categoriesArray);
    }
}
