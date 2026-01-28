<?php

namespace App\Http\Responses\Category;

use App\Http\Responses\ArrayableResponse;

final class ListCategoriesResponse implements ArrayableResponse
{
    /**
     * @param array<int, array<string, mixed>> $categories
     */
    public function __construct(
        private readonly array $categories,
    ) {}

    /**
     * @return  array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'data' => $this->categories,
            'message' => 'Categories found',
        ];
    }
}
