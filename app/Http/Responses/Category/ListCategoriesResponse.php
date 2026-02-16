<?php

namespace App\Http\Responses\Category;

use App\Http\Responses\ArrayableResponse;

final readonly class ListCategoriesResponse implements ArrayableResponse
{
    /**
     * @param array<int, array<string, mixed>> $data
     * @param array<string, int> $meta
     */
    public function __construct(
        public array $data,
        public array $meta,
    ) {}

    /**
     * @return  array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'data' => $this->data,
            'meta' => $this->meta,
            'message' => 'Categories found',
        ];
    }
}
