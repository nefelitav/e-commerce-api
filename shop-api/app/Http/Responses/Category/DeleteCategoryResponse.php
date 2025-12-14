<?php

namespace App\Http\Responses\Category;

use Response;

final class DeleteCategoryResponse extends Response
{
    public function toArray(): array
    {
        return [
            'message' => 'Category deleted successfully',
        ];
    }
}
