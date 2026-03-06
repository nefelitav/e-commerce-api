<?php

namespace App\Http\Responses\Coupon;

use App\Http\Responses\ArrayableResponse;

final readonly class UpdateCouponResponse implements ArrayableResponse
{
    /**
     * @param  array<string, mixed>  $coupon
     */
    public function __construct(
        private array $coupon,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'data' => $this->coupon,
            'message' => 'Coupon updated successfully',
        ];
    }
}
