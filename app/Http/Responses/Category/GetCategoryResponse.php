<?php

namespace App\Http\Responses\Category;

use App\Http\Responses\ArrayableResponse;
use Symfony\Component\HttpFoundation\Response;

final readonly class GetCategoryResponse implements ArrayableResponse
{
    /**
     * @param array<string, mixed> $category
     */
    public function __construct(
        private array $category
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
