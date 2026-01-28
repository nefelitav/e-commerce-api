<?php

namespace App\Http\Responses\Product;

use App\Http\Responses\ArrayableResponse;

final class DeleteProductResponse implements ArrayableResponse
{
    /**
     * @return  array<string, string>
     */
    public function toArray(): array
    {
        return [
            'message' => 'Product deleted successfully',
        ];
    }
}
