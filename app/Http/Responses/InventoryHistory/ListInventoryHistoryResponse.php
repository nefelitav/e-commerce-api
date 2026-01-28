<?php

namespace App\Http\Responses\InventoryHistory;

use App\Http\Responses\ArrayableResponse;

final readonly class ListInventoryHistoryResponse implements ArrayableResponse
{
    /**
     * @param array<int, array<string, mixed>> $entries
     */
    public function __construct(
        private array $entries,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'data' => $this->entries,
            'message' => 'Inventory history found',
        ];
    }
}

