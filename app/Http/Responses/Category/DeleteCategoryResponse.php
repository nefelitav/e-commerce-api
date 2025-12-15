<?php

namespace App\Http\Responses\Category;

use Response;

final class DeleteCategoryResponse implements ArrayableResponse
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
