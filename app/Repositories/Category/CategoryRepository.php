<?php

namespace App\Repositories\Category;

use App\Dto\Category\Category;
use App\Dto\Category\UnpersistedCategory;
use App\Exceptions\CategoryNotFoundException;
use App\Models\Category\CategoryModel;
use Illuminate\Support\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class CategoryRepository
{
    /**
     * @return LengthAwarePaginator<int, Category>
     */
    public function getAll(int $page = 1, int $perPage = 15): LengthAwarePaginator
    {
        $paginator = CategoryModel::query()->paginate($perPage, ['*'], 'page', $page);

        /** @var Collection<int, Category> $items */
        $items = $paginator->getCollection()->map(fn($model) => Category::fromModel($model));

        $paginator->setCollection($items);

        return $paginator;
    }

    /**
     * @return array<Category>
     */
    public function getSubcategoriesById(int $id): array
    {
        $category = CategoryModel::with('children')->find($id);

        if (!$category) {
            return [];
        }

        /** @var Collection<int, CategoryModel> $children */
        $children = $category->children()->get();

        return $children
            ->map(fn(CategoryModel $child) => Category::fromModel($child))
            ->all();
    }


    public function findById(int $id): ?Category
    {
        $category = CategoryModel::with('children')->find($id);

        return $category ? Category::fromModel($category) : null;
    }

    public function findByName(string $name): ?Category
    {
        $categoryModel = CategoryModel::query()->where('name', $name)->first();

        return $categoryModel ? Category::fromModel($categoryModel) : null;
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
