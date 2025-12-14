<?php

namespace App\Repositories\Category;

use App\Dto\Category\Category;
use App\Dto\Category\UnpersistedCategory;
use App\Exceptions\CategoryNotFoundException;
use App\Models\Category\CategoryModel;

class CategoryRepository
{
    /**
     * @return array<Category>
     */
    public function getAll(): array
    {
        $categories = CategoryModel::with('children')->get();

        return $categories->map(fn($model) => Category::fromModel($model))->all();
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

        return $category->children()->get()->map(fn($child) => Category::fromModel($child))->all();
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
