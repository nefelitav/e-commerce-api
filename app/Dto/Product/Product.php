<?php

namespace App\Dto\Product;

use App\Models\Product\ProductModel;

final readonly class Product
{
    public function __construct(
        public int    $id,
        public string  $name,
        public ?string $description,
        public float $price,
        public int $quantity,
        public int    $categoryId,
    ) {}

    public static function fromModel(ProductModel $product): self
    {
        return new self(
            $product->id,
            $product->name,
            $product->description,
            $product->price,
            $product->quantity,
            $product->category_id,
        );
    }

}
