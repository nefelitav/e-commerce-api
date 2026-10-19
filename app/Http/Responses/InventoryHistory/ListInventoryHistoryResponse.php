<?php

namespace App\Http\Responses\InventoryHistory;

use App\Http\Responses\ArrayableResponse;

final readonly class ListInventoryHistoryResponse implements ArrayableResponse
{
    /**
     * @param  array<int, array<string, mixed>>  $entries
     * @param  array<string, int>|null  $meta
     */
    public function __construct(
        private array $entries,
        private ?array $meta = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'data' => $this->entries,
            'meta' => $this->meta,
            'message' => 'Inventory history found',
        ];
    }
}
