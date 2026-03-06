<?php

namespace App\Http\Responses\ReturnRequest;

use App\Http\Responses\ArrayableResponse;

final readonly class ListReturnRequestsResponse implements ArrayableResponse
{
    /**
     * @param  array<int, array<string, mixed>>  $data
     * @param  array<string, int>|null  $meta
     */
    public function __construct(
        public array $data,
        public ?array $meta = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'data' => $this->data,
            'meta' => $this->meta,
            'message' => 'Return requests found',
        ];
    }
}
