<?php

namespace App\Dto\Order;

use App\Enums\OrderStatus;

final readonly class UnpersistedOrder
{
    /**
     * @param  array<int, UnpersistedOrderItem>  $items
     */
    public function __construct(
        public int $userId,
        public OrderStatus $status,
        public float $totalPrice,
        public array $items = [],
        public ?string $couponCode = null,
        public ?int $couponId = null,
        public float $discountAmount = 0.0,
        public ?string $idempotencyKey = null,
    ) {}

    public function withCoupon(int $couponId, float $discountAmount): self
    {
        return new self(
            userId: $this->userId,
            status: $this->status,
            totalPrice: $this->totalPrice,
            items: $this->items,
            couponCode: $this->couponCode,
            couponId: $couponId,
            discountAmount: $discountAmount,
            idempotencyKey: $this->idempotencyKey,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [
            'user_id' => $this->userId,
            'status' => $this->status->value,
            'total_price' => $this->totalPrice,
        ];

        // Only written when set, so status-only updates never wipe them.
        if ($this->couponId !== null) {
            $data['coupon_id'] = $this->couponId;
            $data['discount_amount'] = $this->discountAmount;
        }
        if ($this->idempotencyKey !== null) {
            $data['idempotency_key'] = $this->idempotencyKey;
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
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
            userId: $data['user_id'],
            status: OrderStatus::from($data['status']),
            totalPrice: (float) $data['total_price'],
            items: $items,
        );
    }
}
