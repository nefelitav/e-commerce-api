<?php

namespace App\Http\Requests\Webhook;

use Illuminate\Foundation\Http\FormRequest;

final class PaymentWebhookRequest extends FormRequest
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
            'payment_reference' => ['required', 'string', 'max:255'],
            'status' => ['required', 'string', 'in:paid'],
        ];
    }
}

