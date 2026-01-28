<?php

namespace App\Http\Responses\Order;

use App\Http\Responses\ArrayableResponse;

final readonly class DeleteOrderResponse implements ArrayableResponse
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'data' => [],
            'message' => 'Order deleted successfully',
        ];
    }
}

