<?php

namespace App\Http\Requests\Webhook;

use App\Enums\OrderStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ShippingWebhookRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var string|null $secret */
        $secret = config('webhooks.signing_secret');

        if ($secret === null || $secret === '') {
            return true;
        }

        /** @var string|null $signature */
        $signature = $this->header('X-Webhook-Signature');

        if ($signature === null) {
            return false;
        }

        $expectedSignature = hash_hmac('sha256', (string) $this->getContent(), $secret);

        return hash_equals($expectedSignature, $signature);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'order_id' => ['required', 'integer', 'exists:orders,id'],
            'event' => ['required', 'string', Rule::in([OrderStatus::Shipped->value, OrderStatus::Delivered->value])],
            'tracking_number' => ['required_if:event,'.OrderStatus::Shipped->value, 'nullable', 'string', 'max:255'],
        ];
    }
}
