<?php

namespace App\Http\Responses\Product;

use App\Http\Responses\ArrayableResponse;

final readonly class ListCategoryProductsResponse implements ArrayableResponse
{
    /**
     * @param array<int, array<string, mixed>> $products
     */
    public function __construct(
        private array $products,
    ) {}

    /**
     * @return  array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'data' => $this->products,
            'message' => 'Products found',
        ];
    }
}
