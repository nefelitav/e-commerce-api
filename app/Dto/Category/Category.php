<?php

namespace App\Dto\Category;

use App\Models\Category\CategoryModel;

final readonly class Category
{
    public function __construct(
        public int     $id,
        public string  $name,
        public ?string $description,
        public ?int    $parentId,
    ) {}

    public static function fromModel(CategoryModel $category): self
    {
        return new self(
            $category->id,
            $category->name,
            $category->description,
            $category->parent_id,
        );
    }
}
