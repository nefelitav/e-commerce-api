<?php

namespace App\Dto\Coupon;

use App\Enums\CouponType;

final readonly class UnpersistedCoupon
{
    public function __construct(
        public string $code,
        public CouponType $type,
        public float $value,
        public ?float $minOrderAmount,
        public ?int $maxUses,
        public ?string $expiresAt,
        public bool $isActive = true,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'type' => $this->type->value,
            'value' => $this->value,
            'min_order_amount' => $this->minOrderAmount,
            'max_uses' => $this->maxUses,
            'expires_at' => $this->expiresAt,
            'is_active' => $this->isActive,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            code: $data['code'],
            type: CouponType::from($data['type']),
            value: (float) $data['value'],
            minOrderAmount: isset($data['min_order_amount']) ? (float) $data['min_order_amount'] : null,
            maxUses: isset($data['max_uses']) ? (int) $data['max_uses'] : null,
            expiresAt: $data['expires_at'] ?? null,
            isActive: $data['is_active'] ?? true,
        );
    }
}
