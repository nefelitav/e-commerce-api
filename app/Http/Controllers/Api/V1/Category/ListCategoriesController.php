<?php

namespace App\Http\Controllers\Api\V1\Category;

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

    public function index(): JsonResponse
    {
        $page = (int) request()->query('page', '1');
        $perPage = (int) request()->query('per_page', '15');

        $listCategoriesResponse = $this->executeRequest($page, $perPage);

        return self::success($listCategoriesResponse, Response::HTTP_OK);
    }

    private function executeRequest(int $page, int $perPage): ListCategoriesResponse
    {
        try {
            $categoriesPaginator = $this->service->listCategories($page, $perPage);
        } catch (Exception $e) {
            throw new UnprocessableEntityException($e);
        }

        $categoriesArray = [];
        foreach ($categoriesPaginator->items() as $category) {
            $categoriesArray[] = $this->transformer->transform($category);
        }
        $this->logger->info("Categories found.", ["categories" => $categoriesArray]);

        return new ListCategoriesResponse(
            $categoriesArray,
            [
                'current_page' => $categoriesPaginator->currentPage(),
                'per_page' => $categoriesPaginator->perPage(),
                'total' => $categoriesPaginator->total(),
                'last_page' => $categoriesPaginator->lastPage(),
            ]
        );
    }
}
