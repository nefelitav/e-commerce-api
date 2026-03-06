<?php

namespace App\Enums;

enum CouponType: string
{
    case Percentage = 'percentage';
    case FixedAmount = 'fixed_amount';
}
