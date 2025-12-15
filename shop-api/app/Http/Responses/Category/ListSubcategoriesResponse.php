<?php

namespace App\Http\Responses\Category;

use App\Transformers\CategoryTransformer;
use App\Dto\Category\Category;
use Response;

final class ListSubcategoriesResponse extends Response
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
            'message' => 'Subcategories found',
        ];
    }
}
