<?php

namespace App\Events;

use App\Dto\Order\Order;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class OrderCancelledEvent
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Order $order,
        public readonly bool $refundIssued,
    ) {}
}
