<?php

namespace App\Http\Responses\Webhook;

use App\Http\Responses\ArrayableResponse;

final readonly class PaymentWebhookResponse implements ArrayableResponse
{
    /**
     * @param array<string, mixed> $order
     */
    public function __construct(
        private array $order,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'data' => $this->order,
            'message' => 'Payment processed successfully',
        ];
    }
}

