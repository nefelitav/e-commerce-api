<?php

namespace App\Repositories\Product;

use App\Dto\Product\Product;
use App\Dto\Product\UnpersistedProduct;
use App\Exceptions\ProductNotFoundException;
use App\Models\Product\ProductModel;
use Illuminate\Support\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class ProductRepository
{
    /**
     * @return LengthAwarePaginator<int, Product>
     */
    public function getAll(int $page = 1, int $perPage = 15): LengthAwarePaginator
    {
        $paginator = ProductModel::query()->paginate($perPage, ['*'], 'page', $page);

        /** @var Collection<int, Product> $items */
        $items = $paginator->getCollection()->map(fn($model) => Product::fromModel($model));

        $paginator->setCollection($items);

        return $paginator;
    }

    /**
     * @return array<Product>|null
     */
    public function findByCategoryId(int $categoryId): ?array
    {
        /** @var Collection<int, ProductModel> $products */
        $products = ProductModel::query()->where('category_id', $categoryId)->get();

        if ($products->isEmpty()) {
            return [];
        }

        return $products->map(fn(ProductModel $model) => Product::fromModel($model))->all();
    }

    public function findById(int $id): ?Product
    {
        /** @var ProductModel|null $product */
        $product = ProductModel::find($id);

        return $product ? Product::fromModel($product) : null;
    }

    public function findByName(string $name): ?Product
    {
        /** @var ProductModel|null $productModel */
        $productModel = ProductModel::query()->where('name', $name)->first();

        return $productModel ? Product::fromModel($productModel) : null;
    }

    public function persist(UnpersistedProduct $unpersistedProduct): Product
    {
        /** @var ProductModel $productModel */
        $productModel = ProductModel::create($unpersistedProduct->toArray());

        return Product::fromModel($productModel);
    }

    /**
     * @throws ProductNotFoundException
     */
    public function update(int $id, UnpersistedProduct $unpersistedProduct): Product
    {
        /** @var ProductModel|null $productModel */
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
        /** @var ProductModel|null $productModel */
        $productModel = ProductModel::query()->where('id', $id)->first();

        if (!$productModel) {
            throw new ProductNotFoundException($id);
        }

        return $productModel->delete();
    }
}
