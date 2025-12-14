<?php

namespace App\Http\Responses\Category;

use App\Transformers\CategoryTransformer;
use App\Dto\Category\Category;
use Response;

final class CreateCategoryResponse extends Response
{
    public function __construct(
        private readonly CategoryTransformer $transformer,
        private readonly Category $category
    ) {}

    public function toArray(): array
    {
        return [
            'data' => $this->transformer->transform($this->category),
            'message' => 'Category updated successfully',
        ];
    }
}
