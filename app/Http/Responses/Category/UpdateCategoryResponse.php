<?php

namespace App\Http\Responses\Category;

use App\Http\Responses\ArrayableResponse;

final readonly class UpdateCategoryResponse implements ArrayableResponse
{
    /**
     * @param array<string, mixed> $category
     */
    public function __construct(
        private array $category
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
