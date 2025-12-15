<?php

namespace App\Http\Responses\Category;

use Response;

final class CreateCategoryResponse extends Response
{
    /**
     * @param array<string, mixed> $category
     */
    public function __construct(
        private readonly array $category
    ) {}

    public function toArray(): array
    {
        return [
            'data' => $this->category,
            'message' => 'Category updated successfully',
        ];
    }
}
