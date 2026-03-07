<?php

namespace App\Enums;

enum OrderStatus: string
{
    case Pending = 'pending';
    case PaymentFailed = 'payment_failed';
    case Paid = 'paid';
    case Processing = 'processing';
    case Shipped = 'shipped';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';
    case Refunded = 'refunded';
}
