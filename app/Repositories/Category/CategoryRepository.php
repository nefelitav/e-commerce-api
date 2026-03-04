<?php

namespace App\Repositories\Category;

use App\Dto\Category\Category;
use App\Dto\Category\UnpersistedCategory;
use App\Exceptions\CategoryNotFoundException;
use App\Models\Category\CategoryModel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;

class CategoryRepository implements CategoryRepositoryInterface
{
    private const TTL = 1800;

    /**
     * @param array<string, mixed> $filters
     * @param array<string> $includes
     * @return LengthAwarePaginator<int, Category>
     */
    public function getAll(
        int $page = 1,
        int $perPage = 15,
        string $sort = 'id',
        string $order = 'asc',
        array $filters = [],
        array $includes = []
    ): LengthAwarePaginator {
        $cacheKey = 'categories.all.' . md5(serialize([$page, $perPage, $sort, $order, $filters, $includes]));

        /** @var LengthAwarePaginator<int, Category> $result */
        $result = Cache::tags(['categories'])->remember($cacheKey, self::TTL, function () use ($page, $perPage, $sort, $order, $filters, $includes) {
            $query = CategoryModel::query();

            if (!empty($includes)) {
                $query->with($includes);
            }

            if (isset($filters['name'])) {
                $query->where('name', 'like', '%' . $filters['name'] . '%');
            }
            if (isset($filters['parent_id'])) {
                $query->where('parent_id', $filters['parent_id']);
            }

            $query->orderBy($sort, $order);

            $paginator = $query->paginate($perPage, ['*'], 'page', $page);

            $items = $paginator->getCollection()->map(fn(CategoryModel $model) => Category::fromModel($model));

            return new LengthAwarePaginator(
                $items,
                $paginator->total(),
                $paginator->perPage(),
                $paginator->currentPage(),
                ['path' => LengthAwarePaginator::resolveCurrentPath()],
            );
        });

        return $result;
    }

    /**
     * @return array<Category>
     */
    public function getSubcategoriesById(int $id): array
    {
        $cacheKey = "categories.{$id}.children";

        /** @var array<Category> $result */
        $result = Cache::tags(['categories'])->remember($cacheKey, self::TTL, function () use ($id) {
            $category = CategoryModel::with('children')->find($id);

            if (!$category) {
                return [];
            }

            /** @var Collection<int, CategoryModel> $children */
            $children = $category->children()->get();

            return $children
                ->map(fn(CategoryModel $child) => Category::fromModel($child))
                ->all();
        });

        return $result;
    }

    public function findById(int $id): ?Category
    {
        $cacheKey = "categories.{$id}";

        /** @var Category|null $result */
        $result = Cache::tags(['categories'])->remember($cacheKey, self::TTL, function () use ($id) {
            $category = CategoryModel::with('children')->find($id);

            return $category ? Category::fromModel($category) : null;
        });

        return $result;
    }

    public function findByName(string $name): ?Category
    {
        $cacheKey = 'categories.name.' . md5($name);

        /** @var Category|null $result */
        $result = Cache::tags(['categories'])->remember($cacheKey, self::TTL, function () use ($name) {
            $categoryModel = CategoryModel::query()->where('name', $name)->first();

            return $categoryModel ? Category::fromModel($categoryModel) : null;
        });

        return $result;
    }

    public function persist(UnpersistedCategory $unpersistedCategory): Category
    {
        $categoryModel = CategoryModel::create($unpersistedCategory->toArray());

        return Category::fromModel($categoryModel);
    }

    /**
     * @throws CategoryNotFoundException
     */
    public function update(int $id, UnpersistedCategory $unpersistedCategory): Category
    {
        $categoryModel = CategoryModel::query()->where('id', $id)->first();

        if (!$categoryModel) {
            throw new CategoryNotFoundException($id);
        }

        $categoryModel->update($unpersistedCategory->toArray());

        $categoryModel->refresh();

        return Category::fromModel($categoryModel);
    }

    /**
     * @throws CategoryNotFoundException
     */
    public function delete(int $id): bool
    {
        $categoryModel = CategoryModel::query()->where('id', $id)->first();

        if (!$categoryModel) {
            throw new CategoryNotFoundException($id);
        }

        return $categoryModel->delete();
    }
}
