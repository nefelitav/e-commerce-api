<?php

namespace App\Http\Responses\Order;

use App\Http\Responses\ArrayableResponse;

final readonly class ListOrdersResponse implements ArrayableResponse
{
    /**
     * @param array<int, array<string, mixed>> $orders
     */
    public function __construct(
        private array $orders,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'data' => $this->orders,
            'message' => 'Orders found',
        ];
    }
}

