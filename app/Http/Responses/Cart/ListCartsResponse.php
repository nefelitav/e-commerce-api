<?php

namespace App\Http\Responses\Cart;

use App\Http\Responses\ArrayableResponse;

final readonly class ListCartsResponse implements ArrayableResponse
{
    /**
     * @param array<int, array<string, mixed>> $carts
     */
    public function __construct(
        private array $carts,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'data' => $this->carts,
            'message' => 'Carts found',
        ];
    }
}
