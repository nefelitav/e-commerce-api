<?php

namespace App\Http\Responses\Webhook;

use App\Enums\OrderStatus;
use App\Http\Responses\ArrayableResponse;

final readonly class ShippingWebhookResponse implements ArrayableResponse
{
    /**
     * @param  array<string, mixed>  $order
     */
    public function __construct(
        private array $order,
        private string $event,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'data' => $this->order,
            'message' => match ($this->event) {
                OrderStatus::Shipped->value => 'Order marked as shipped',
                OrderStatus::Delivered->value => 'Order marked as delivered',
                default => 'Shipping event processed',
            },
        ];
    }
}
