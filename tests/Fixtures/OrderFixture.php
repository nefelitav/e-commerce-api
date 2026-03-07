<?php

namespace Tests\Fixtures;

use App\Enums\OrderStatus;
use App\Models\Product\ProductModel;

class OrderFixture
{
    /**
     * Build an order payload for a single product.
     *
     * @return array{status: string, total_price: float, items: array<int, array{product_id: int, quantity: int, unit_price: float}>}
     */
    public static function payload(ProductModel $product, int $quantity = 1, ?string $status = null): array
    {
        return [
            'status' => $status ?? OrderStatus::Pending->value,
            'total_price' => $product->price * $quantity,
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => $quantity,
                    'unit_price' => $product->price,
                ],
            ],
        ];
    }

    /**
     * Build an order payload with multiple items.
     *
     * @param  array<int, array{product: ProductModel, quantity: int, unit_price?: float}>  $items
     * @return array{status: string, total_price: float|int, items: array<int, array{product_id: int, quantity: int, unit_price: float}>}
     */
    public static function multiItemPayload(array $items, ?string $status = null): array
    {
        $orderItems = [];
        $totalPrice = 0;

        foreach ($items as $item) {
            $product = $item['product'];
            $qty = $item['quantity'];
            $unitPrice = array_key_exists('unit_price', $item) ? $item['unit_price'] : $product->price;

            $orderItems[] = [
                'product_id' => $product->id,
                'quantity' => $qty,
                'unit_price' => $unitPrice,
            ];
            $totalPrice += $unitPrice * $qty;
        }

        return [
            'status' => $status ?? OrderStatus::Pending->value,
            'total_price' => $totalPrice,
            'items' => $orderItems,
        ];
    }

    /**
     * Build a webhook payment payload.
     *
     * @return array{order_id: int, payment_reference: string, status: string}
     */
    public static function webhookPayload(int $orderId, string $reference = 'pay_test_001', ?string $status = null): array
    {
        return [
            'order_id' => $orderId,
            'payment_reference' => $reference,
            'status' => $status ?? OrderStatus::Paid->value,
        ];
    }

    /**
     * Build a signed webhook: returns [payload, headers].
     *
     * @return array{payload: array{order_id: int, payment_reference: string, status: string}, headers: array<string, string>}
     */
    public static function signedWebhookPayload(int $orderId, string $secret, string $reference = 'pay_signed_001'): array
    {
        $payload = [
            'order_id' => $orderId,
            'payment_reference' => $reference,
            'status' => OrderStatus::Paid->value,
        ];

        $encoded = json_encode($payload, JSON_THROW_ON_ERROR);
        $signature = hash_hmac('sha256', $encoded, $secret);

        return [
            'payload' => $payload,
            'headers' => ['X-Webhook-Signature' => $signature],
        ];
    }
}
