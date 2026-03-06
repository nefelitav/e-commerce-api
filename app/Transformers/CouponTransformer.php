<?php

namespace App\Transformers;

use App\Dto\Coupon\Coupon;

final readonly class CouponTransformer
{
    /**
     * @return array<string, mixed>
     */
    public function transform(Coupon $coupon): array
    {
        return [
            'id' => $coupon->id,
            'code' => $coupon->code,
            'type' => $coupon->type->value,
            'value' => $coupon->value,
            'min_order_amount' => $coupon->minOrderAmount,
            'max_uses' => $coupon->maxUses,
            'times_used' => $coupon->timesUsed,
            'expires_at' => $coupon->expiresAt,
            'is_active' => $coupon->isActive,
        ];
    }
}
