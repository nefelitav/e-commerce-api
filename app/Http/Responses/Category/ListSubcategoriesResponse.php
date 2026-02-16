<?php

namespace App\Http\Responses\Category;

use App\Http\Responses\ArrayableResponse;
use App\Transformers\CategoryTransformer;
use App\Dto\Category\Category;
use Response;

final readonly class ListSubcategoriesResponse implements ArrayableResponse
{
    /**
     * @param array<int, array<string, mixed>> $categories
     */
    public function __construct(
        private array $categories,
    ) {}

    /**
     * @return  array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'data' => $this->categories,
            'message' => 'Subcategories found',
        ];
    }
}
