<?php

namespace App\Dto\Cart;

final readonly class UnpersistedCart
{
    /**
     * @param array<int, UnpersistedCartItem> $items
     */
    public function __construct(
        public int $userId,
        public array $items = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'user_id' => $this->userId,
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $items = [];
        if (isset($data['items']) && is_array($data['items'])) {
            /** @var array<int, array<string, mixed>> $rawItems */
            $rawItems = $data['items'];
            foreach ($rawItems as $rawItem) {
                $items[] = UnpersistedCartItem::fromArray($rawItem);
            }
        }

        return new self(
            $data['user_id'],
            $items,
        );
    }
}
