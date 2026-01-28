<?php

namespace App\Transformers;

use App\Dto\Product\Product;

final readonly class ProductTransformer
{
    /**
     * @return array<string, mixed>
     */
    public function transform(Product $product): array
    {
        return [
            'id' => $product->id,
            'name' => $product->name,
            'price' => $product->price,
            'quantity' => $product->quantity,
            'description' => $product->description,
            'category_id' => $product->categoryId,
        ];
    }
}
