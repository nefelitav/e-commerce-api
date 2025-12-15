<?php

namespace App\Transformers;

use App\Dto\Category\Category;

final readonly class CategoryTransformer
{
    /**
     * @return array<string, mixed>
     */
    public function transform(Category $category): array
    {
        return [
            'id' => $category->id,
            'name' => $category->name,
            'description' => $category->description,
            'parent_id' => $category->parentId,
        ];
    }
}
