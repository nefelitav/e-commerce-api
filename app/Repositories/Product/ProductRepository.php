<?php

namespace App\Repositories\Product;

use App\Dto\Product\Product;
use App\Dto\Product\UnpersistedProduct;
use App\Exceptions\ProductNotFoundException;
use App\Models\Product\ProductModel;

class ProductRepository
{
    /**
     * @return array<Product>
     */
    public function getAll(): array
    {
        $products = ProductModel::with('children')->get();

        return $products->map(fn($model) => Product::fromModel($model))->all();
    }

    /**
     * @return array<Product>|null
     */
    public function findByCategoryId(int $categoryId): ?array
    {
        $products = ProductModel::query()->where('category_id', $categoryId)->get();

        if ($products->isEmpty()) {
            return null;
        }

        return $products->map(fn($model) => Product::fromModel($model))->all();
    }

    public function findById(int $id): ?Product
    {
        $product = ProductModel::with('children')->find($id);

        return $product ? Product::fromModel($product) : null;
    }

    public function findByName(string $name): ?Product
    {
        $productModel = ProductModel::query()->where('name', $name)->first();

        return $productModel ? Product::fromModel($productModel) : null;
    }

    public function persist(UnpersistedProduct $unpersistedProduct): Product
    {
        $productModel = ProductModel::create($unpersistedProduct->toArray());

        return Product::fromModel($productModel);
    }

    /**
     * @throws ProductNotFoundException
     */
    public function update(int $id, UnpersistedProduct $unpersistedProduct): Product
    {
        $productModel = ProductModel::query()->where('id', $id)->first();

        if (!$productModel) {
            throw new ProductNotFoundException($id);
        }

        $productModel->update($unpersistedProduct->toArray());

        $productModel->refresh();

        return Product::fromModel($productModel);
    }

    /**
     * @throws ProductNotFoundException
     */
    public function delete(int $id): bool
    {
        $productModel = ProductModel::query()->where('id', $id)->first();

        if (!$productModel) {
            throw new ProductNotFoundException($id);
        }

        return $productModel->delete();
    }
}
