<?php

namespace App\Http\Responses\Category;

use App\Transformers\CategoryTransformer;
use App\Dto\Category\Category;
use Response;

final class ListCategoriesResponse extends Response
{
    /**
     * @param array<Category> $categories
     */
    public function __construct(
        private readonly CategoryTransformer $transformer,
        private readonly array $categories,
    ) {}

    public function toArray(): array
    {
        return [
            'data' => array_map(
                fn(Category $category) => $this->transformer->transform($category),
                $this->categories
            ),
            'message' => 'Categories found',
        ];
    }
}
