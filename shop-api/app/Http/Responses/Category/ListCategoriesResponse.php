<?php

namespace App\Http\Responses\Category;

use Response;

final class ListCategoriesResponse extends Response
{
    /**
     * @param array<int, array<string, mixed>> $categories
     */
    public function __construct(
        private readonly array $categories,
    ) {}

    public function toArray(): array
    {
        return [
            'data' => $this->categories,
            'message' => 'Categories found',
        ];
    }
}
