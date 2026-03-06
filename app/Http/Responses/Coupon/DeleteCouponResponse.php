<?php

namespace App\Http\Responses\Coupon;

use App\Http\Responses\ArrayableResponse;

final readonly class DeleteCouponResponse implements ArrayableResponse
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'message' => 'Coupon deleted successfully',
        ];
    }
}
