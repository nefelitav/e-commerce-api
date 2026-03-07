<?php

namespace Tests\DataProviders;

use App\Enums\OrderStatus;

/**
 * Reusable data providers for security tests.
 */
final class SecurityDataProvider
{
    /**
     * @return array<string, array{0: string}>
     */
    public static function sqlInjectionPayloads(): array
    {
        return [
            'drop table' => ["'; DROP TABLE products; --"],
            'union select' => ["' UNION SELECT * FROM users --"],
            'or 1=1' => ["' OR '1'='1"],
            'comment bypass' => ["admin'--"],
            'semicolon chain' => ["'; SELECT * FROM users WHERE '1'='1"],
            'hex encoded' => ['0x27204F522031'],
            'double quote' => ['" OR ""="'],
        ];
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function xssPayloads(): array
    {
        return [
            'script tag' => ['<script>alert("xss")</script>'],
            'img onerror' => ['<img src=x onerror=alert("xss")>'],
            'svg onload' => ['<svg onload=alert("xss")>'],
            'javascript protocol' => ['javascript:alert("xss")'],
            'event handler' => ['<div onmouseover="alert(1)">hover</div>'],
            'cookie steal' => ['<script>document.cookie</script>'],
        ];
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function invalidOrderStatuses(): array
    {
        return [
            'random string' => ['invalid_status'],
            'sql injection' => ["'; DROP TABLE orders; --"],
            'numeric' => ['123'],
            'empty string' => [''],
            'boolean string' => ['true'],
            'uppercase valid' => ['PENDING'],
        ];
    }

    /**
     * @return array<string, array{0: mixed}>
     */
    public static function typeConfusionForNumericFields(): array
    {
        return [
            'string text' => ['not-a-number'],
            'boolean true' => [true],
            'boolean false' => [false],
            'null' => [null],
            'array' => [[1, 2, 3]],
            'object-like array' => [['value' => 10]],
            'empty string' => [''],
        ];
    }

    /**
     * @return array<string, array{0: mixed}>
     */
    public static function typeConfusionForStringFields(): array
    {
        return [
            'integer' => [12345],
            'boolean true' => [true],
            'boolean false' => [false],
            'array' => [['an', 'array']],
            'null' => [null],
            'nested object' => [['key' => 'value']],
        ];
    }

    /**
     * @return array<string, array{0: mixed}>
     */
    public static function invalidProductPrices(): array
    {
        return [
            'negative' => [-10.00],
            'zero' => [0],
            'non-numeric string' => ['not-a-number'],
            'boolean' => [true],
        ];
    }

    /**
     * @return array<string, array{0: mixed}>
     */
    public static function invalidProductQuantities(): array
    {
        return [
            'negative' => [-1],
            'non-integer string' => ['five'],
            'boolean' => [false],
        ];
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function protectedEndpoints(): array
    {
        return [
            'create order' => ['POST', 'v1.orders.store'],
            'list orders' => ['GET', 'v1.orders.index'],
            'create product' => ['POST', 'v1.products.store'],
            'create category' => ['POST', 'v1.categories.store'],
        ];
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: array<string, mixed>}>
     */
    public static function adminOnlyEndpoints(): array
    {
        return [
            'create product' => ['POST', 'v1.products.store', ['name' => 'Test', 'price' => 10, 'quantity' => 5]],
            'create category' => ['POST', 'v1.categories.store', ['name' => 'Test Category']],
        ];
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function nonPayableOrderStatuses(): array
    {
        return [
            'shipped' => [OrderStatus::Shipped->value],
            'cancelled' => [OrderStatus::Cancelled->value],
            'delivered' => [OrderStatus::Delivered->value],
        ];
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function invalidTransitionsFromPending(): array
    {
        return [
            'pending to shipped' => [OrderStatus::Shipped->value],
            'pending to delivered' => [OrderStatus::Delivered->value],
            'pending to paid' => [OrderStatus::Paid->value],
        ];
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function invalidWebhookStatuses(): array
    {
        return [
            'shipped' => [OrderStatus::Shipped->value],
            'delivered' => [OrderStatus::Delivered->value],
            'pending' => [OrderStatus::Pending->value],
            'cancelled' => [OrderStatus::Cancelled->value],
            'random' => ['random_status'],
        ];
    }
}
