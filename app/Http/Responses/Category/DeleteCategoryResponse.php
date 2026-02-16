<?php

namespace App\Http\Responses\Category;

use App\Http\Responses\ArrayableResponse;

final readonly class DeleteCategoryResponse implements ArrayableResponse
{
    /**
     * @return  array<string, string>
     */
    public function toArray(): array
    {
        return [
            'message' => 'Category deleted successfully',
        ];
    }
}
