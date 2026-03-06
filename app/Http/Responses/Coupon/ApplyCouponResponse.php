<?php

namespace App\Http\Responses\Coupon;

use App\Http\Responses\ArrayableResponse;

final readonly class ApplyCouponResponse implements ArrayableResponse
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        private array $data,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'data' => $this->data,
            'message' => 'Coupon applied successfully',
        ];
    }
}
