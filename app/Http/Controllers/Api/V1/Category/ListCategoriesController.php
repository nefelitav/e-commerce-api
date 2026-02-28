<?php

namespace App\Http\Controllers\Api\V1\Category;

use App\Exceptions\UnprocessableEntityException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Category\ListCategoriesRequest;
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

    public function index(ListCategoriesRequest $request): JsonResponse
    {
        /** @var array<string, mixed> $validated */
        $validated = $request->validated();

        $listCategoriesResponse = $this->executeRequest(
            $validated['page'],
            $validated['per_page'],
            $validated['sort'],
            $validated['order'],
            $validated['filter'],
            $validated['include']
        );

        return self::success($listCategoriesResponse, Response::HTTP_OK);
    }

    /**
     * @param array<string, mixed> $filters
     * @param array<string> $includes
     */
    private function executeRequest(
        int $page,
        int $perPage,
        string $sort,
        string $order,
        array $filters,
        array $includes
    ): ListCategoriesResponse {
        try {
            $categoriesPaginator = $this->service->listCategories(
                $page,
                $perPage,
                $sort,
                $order,
                $filters,
                $includes
            );
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
