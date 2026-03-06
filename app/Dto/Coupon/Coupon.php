<?php

namespace App\Dto\Coupon;

use App\Enums\CouponType;
use App\Models\Coupon\CouponModel;

final readonly class Coupon
{
    public function __construct(
        public int $id,
        public string $code,
        public CouponType $type,
        public float $value,
        public ?float $minOrderAmount,
        public ?int $maxUses,
        public int $timesUsed,
        public ?string $expiresAt,
        public bool $isActive,
    ) {}

    public static function fromModel(CouponModel $model): self
    {
        return new self(
            id: $model->id,
            code: $model->code,
            type: $model->type,
            value: $model->value,
            minOrderAmount: $model->min_order_amount,
            maxUses: $model->max_uses,
            timesUsed: $model->times_used,
            expiresAt: $model->expires_at?->toIso8601String(),
            isActive: $model->is_active,
        );
    }
}
