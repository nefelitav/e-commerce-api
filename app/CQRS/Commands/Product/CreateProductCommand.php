<?php

namespace App\CQRS\Commands\Product;

use App\CQRS\Commands\CommandInterface;

final readonly class CreateProductCommand implements CommandInterface
{
    public function __construct(
        public string  $name,
        public ?string $description,
        public float   $price,
        public int     $quantity,
        public int     $categoryId,
    ) {}
}

