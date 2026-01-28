<?php

namespace App\Http\Responses\Product;

use App\Http\Responses\ArrayableResponse;

final readonly class UpdateProductResponse implements ArrayableResponse
{
    /**
     * @param array<string, mixed> $product
     */
    public function __construct(
        private array $product
    ) {}

    /**
     * @return  array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'data' => $this->product,
            'message' => 'Product updated successfully',
        ];
    }
}
