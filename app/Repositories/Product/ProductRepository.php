<?php

namespace App\Repositories\Product;

use App\Dto\Product\Product;
use App\Dto\Product\UnpersistedProduct;
use App\Exceptions\ProductNotFoundException;
use App\Models\Product\ProductModel;
use Illuminate\Support\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ProductRepository
{
    private const TTL = 300;

    /**
     * @param array<string, mixed> $filters
     * @param array<string> $includes
     * @return LengthAwarePaginator<int, Product>
     */
    public function getAll(
        int $page = 1,
        int $perPage = 15,
        string $sort = 'id',
        string $order = 'asc',
        array $filters = [],
        array $includes = []
    ): LengthAwarePaginator {
        $cacheKey = 'products.all.' . md5(serialize([$page, $perPage, $sort, $order, $filters, $includes]));

        /** @var LengthAwarePaginator<int, Product> $result */
        $result = Cache::tags(['products'])->remember($cacheKey, self::TTL, function () use ($page, $perPage, $sort, $order, $filters, $includes) {
            $query = ProductModel::query();

            if (!empty($includes)) {
                $query->with($includes);
            }

            if (isset($filters['name'])) {
                $query->where('name', 'like', '%' . $filters['name'] . '%');
            }
            if (isset($filters['search'])) {
                $term = $filters['search'];
                $query->where(function ($q) use ($term) {
                    $q->where('name', 'like', '%' . $term . '%')
                      ->orWhere('description', 'like', '%' . $term . '%');
                });
            }
            if (isset($filters['category_id'])) {
                $query->where('category_id', $filters['category_id']);
            }
            if (isset($filters['category_ids'])) {
                /** @var array<int, int> $ids */
                $ids = array_map('intval', explode(',', $filters['category_ids']));
                $query->whereIn('category_id', $ids);
            }
            if (isset($filters['min_price'])) {
                $query->where('price', '>=', $filters['min_price']);
            }
            if (isset($filters['max_price'])) {
                $query->where('price', '<=', $filters['max_price']);
            }
            if (isset($filters['min_quantity'])) {
                $query->where('quantity', '>=', $filters['min_quantity']);
            }
            if (isset($filters['max_quantity'])) {
                $query->where('quantity', '<=', $filters['max_quantity']);
            }

            $query->orderBy($sort, $order);

            $paginator = $query->paginate($perPage, ['*'], 'page', $page);

            /** @var Collection<int, Product> $items */
            $items = $paginator->getCollection()->map(fn($model) => Product::fromModel($model));

            $paginator->setCollection($items);

            return $paginator;
        });

        return $result;
    }

    /**
     * @return array<Product>|null
     */
    public function findByCategoryId(int $categoryId): ?array
    {
        $cacheKey = "products.category.{$categoryId}";

        /** @var array<Product>|null $result */
        $result = Cache::tags(['products'])->remember($cacheKey, self::TTL, function () use ($categoryId) {
            /** @var Collection<int, ProductModel> $products */
            $products = ProductModel::query()->where('category_id', $categoryId)->get();

            if ($products->isEmpty()) {
                return [];
            }

            return $products->map(fn(ProductModel $model) => Product::fromModel($model))->all();
        });

        return $result;
    }

    public function findById(int $id): ?Product
    {
        $cacheKey = "products.{$id}";

        /** @var Product|null $result */
        $result = Cache::tags(['products'])->remember($cacheKey, self::TTL, function () use ($id) {
            /** @var ProductModel|null $product */
            $product = ProductModel::find($id);

            return $product ? Product::fromModel($product) : null;
        });

        return $result;
    }

    /**
     * Fetch a product row with a pessimistic write lock (FOR UPDATE).
     */
    public function findByIdForUpdate(int $id): ?ProductModel
    {
        assert(
            DB::transactionLevel() > 0,
            'findByIdForUpdate must be called inside a DB transaction.'
        );

        /** @var ProductModel|null $product */
        $product = ProductModel::query()
            ->where('id', $id)
            ->lockForUpdate()
            ->first();

        return $product;
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
        /** @var Product $updated */
        $updated = DB::transaction(function () use ($id, $unpersistedProduct) {
            /** @var ProductModel|null $productModel */
            $productModel = ProductModel::query()->where('id', $id)->lockForUpdate()->first();

            if (!$productModel) {
                throw new ProductNotFoundException($id);
            }

            $productModel->update($unpersistedProduct->toArray());

            $productModel->refresh();

            return Product::fromModel($productModel);
        });

        return $updated;
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
