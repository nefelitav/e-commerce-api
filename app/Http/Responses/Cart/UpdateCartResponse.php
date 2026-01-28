<?php

namespace App\Http\Responses\Cart;

use App\Http\Responses\ArrayableResponse;

final readonly class UpdateCartResponse implements ArrayableResponse
{
    /**
     * @param array<string, mixed> $cart
     */
    public function __construct(
        private array $cart
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'data' => $this->cart,
            'message' => 'Cart updated successfully',
        ];
    }
}
