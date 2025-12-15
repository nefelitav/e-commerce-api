<?php

namespace App\Dto\Category;

final readonly class UnpersistedCategory
{
    public function __construct(
        public string  $name,
        public ?string $description,
        public ?int    $parentId,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'description' => $this->description,
            'parent_id' => $this->parentId,
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
            $data['parent_id'],
        );
    }
}
