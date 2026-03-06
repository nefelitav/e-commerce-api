<?php

namespace Tests\Fixtures;

use App\Models\Category\CategoryModel;
use App\Models\Product\ProductModel;
use Illuminate\Database\Eloquent\Collection;

class CatalogFixture
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public static function category(array $attributes = []): CategoryModel
    {
        return CategoryModel::factory()->create($attributes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public static function subcategory(CategoryModel $parent, array $attributes = []): CategoryModel
    {
        return CategoryModel::factory()->create(array_merge(
            ['parent_id' => $parent->id],
            $attributes,
        ));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public static function product(array $attributes = []): ProductModel
    {
        return ProductModel::factory()->create($attributes);
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    public static function productWithStock(int $quantity = 10, float $price = 100.00, array $extra = []): ProductModel
    {
        return ProductModel::factory()->create(array_merge([
            'quantity' => $quantity,
            'price' => $price,
        ], $extra));
    }

    /**
     * @param  array<string, mixed>  $categoryAttrs
     * @param  array<string, mixed>  $productAttrs
     * @return array{category: CategoryModel, product: ProductModel}
     */
    public static function simpleProductInCategory(
        float $price = 100.00,
        int $quantity = 10,
        array $categoryAttrs = [],
        array $productAttrs = [],
    ): array {
        $category = self::category($categoryAttrs);
        $product = self::product(array_merge([
            'price' => $price,
            'quantity' => $quantity,
            'category_id' => $category->id,
        ], $productAttrs));

        return compact('category', 'product');
    }

    /**
     * @param  array<string, mixed>  $categoryAttrs
     * @param  array<string, mixed>  $productAttrs
     * @return array{category: CategoryModel, products: Collection<int, ProductModel>}
     */
    public static function productsInCategory(int $count = 5, array $categoryAttrs = [], array $productAttrs = []): array
    {
        $category = self::category($categoryAttrs);
        $products = ProductModel::factory()->count($count)->create(array_merge(
            ['category_id' => $category->id],
            $productAttrs,
        ));

        return compact('category', 'products');
    }

    /**
     * @param  array<string, mixed>  $parentAttrs
     * @return array{parent: CategoryModel, subcategories: CategoryModel[]}
     */
    public static function categoryHierarchy(int $subcategoryCount = 3, array $parentAttrs = []): array
    {
        $parent = self::category($parentAttrs);
        $subcategories = [];

        for ($i = 0; $i < $subcategoryCount; $i++) {
            $subcategories[] = self::subcategory($parent);
        }

        return compact('parent', 'subcategories');
    }
}
