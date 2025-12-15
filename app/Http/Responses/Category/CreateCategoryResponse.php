<?php

namespace App\Http\Responses\Category;

final class CreateCategoryResponse implements ArrayableResponse
{
    /**
     * @param array<string, mixed> $category
     */
    public function __construct(
        private readonly array $category
    ) {}

    /**
     * @return  array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'data' => $this->category,
            'message' => 'Category updated successfully',
        ];
    }
}
