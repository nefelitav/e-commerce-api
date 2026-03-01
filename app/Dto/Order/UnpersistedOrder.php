<?php

namespace App\Dto\Order;

use App\Enums\OrderStatus;

final readonly class UnpersistedOrder
{
    /**
     * @param array<int, UnpersistedOrderItem> $items
     */
    public function __construct(
        public int $userId,
        public OrderStatus $status,
        public float $totalPrice,
        public array $items = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'user_id'     => $this->userId,
            'status'      => $this->status->value,
            'total_price' => $this->totalPrice,
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
                $items[] = UnpersistedOrderItem::fromArray($rawItem);
            }
        }

        return new self(
            userId:     $data['user_id'],
            status:     OrderStatus::from($data['status']),
            totalPrice: (float) $data['total_price'],
            items:      $items,
        );
    }
}
