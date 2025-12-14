<?php

namespace App\Dto\Category;

final readonly class UnpersistedCategory
{
    public function __construct(
        public string  $name,
        public ?string $description,
        public ?int    $parentId,
    ) {}

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'description' => $this->description,
            'parentId' => $this->parentId,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            $data['name'],
            $data['description'],
            $data['parentId'],
        );
    }
}
