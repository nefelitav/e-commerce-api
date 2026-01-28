<?php

namespace App\Dto\Product;

final readonly class UnpersistedProduct
{
    public function __construct(
        public string  $name,
        public ?string $description,
        public float   $price,
        public int     $quantity,
        public ?int    $categoryId,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'description' => $this->description,
            'price' => $this->price,
            'quanity' => $this->quantity,
            'category_id' => $this->categoryId,
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            $data['name'],
            $data['description'],
            $data['price'],
            $data['quanity'],
            $data['category_id'],
        );
    }
}
