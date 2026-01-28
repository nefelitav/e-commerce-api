<?php

namespace App\Http\Responses\Order;

use App\Http\Responses\ArrayableResponse;

final readonly class CreateOrderResponse implements ArrayableResponse
{
    /**
     * @param array<string, mixed> $order
     */
    public function __construct(
        private array $order
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'data' => $this->order,
            'message' => 'Order created successfully',
        ];
    }
}

