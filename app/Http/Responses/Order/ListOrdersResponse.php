<?php

namespace App\Http\Responses\Order;

use App\Http\Responses\ArrayableResponse;

final readonly class ListOrdersResponse implements ArrayableResponse
{
    /**
     * @param array<int, array<string, mixed>> $orders
     * @param array<string, mixed> $meta
     */
    public function __construct(
        private array $orders,
        private array $meta = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $response = [
            'data' => $this->orders,
            'message' => 'Orders found',
        ];

        if (!empty($this->meta)) {
            $response['meta'] = $this->meta;
        }

        return $response;
    }
}
