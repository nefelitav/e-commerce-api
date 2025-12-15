<?php

namespace App\Http\Responses\Category;

use Symfony\Component\HttpFoundation\Response;

final class GetCategoryResponse extends Response
{
    /**
     * @param array<string, mixed> $category
     */
    public function __construct(
        private readonly array $category
    ) {
        parent::__construct();
    }

    public function toArray(): array
    {
        return [
            'data' => $this->category,
            'message' => 'Category found',
        ];
    }
}
