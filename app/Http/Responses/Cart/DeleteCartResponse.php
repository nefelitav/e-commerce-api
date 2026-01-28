<?php

namespace App\Http\Responses\Cart;

use App\Http\Responses\ArrayableResponse;

final readonly class DeleteCartResponse implements ArrayableResponse
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'message' => 'Cart deleted successfully',
        ];
    }
}
