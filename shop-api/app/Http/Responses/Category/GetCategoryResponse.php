<?php

namespace App\Http\Responses\Category;

use Symfony\Component\HttpFoundation\Response;

final class GetCategoryResponse implements ArrayableResponse
{
    /**
     * @param array<string, mixed> $category
     */
    public function __construct(
        private readonly array $category
    ) {
    }

    /**
     * @return  array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'data' => $this->category,
            'message' => 'Category found',
        ];
    }
}
