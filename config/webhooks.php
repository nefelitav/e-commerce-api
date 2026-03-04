<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Webhook URLs
    |--------------------------------------------------------------------------
    |
    | Configure external webhook endpoints that receive event notifications.
    | Set to null or empty string to disable a webhook.
    |
    */

    'order_paid_url' => env('WEBHOOK_ORDER_PAID_URL'),

    /*
    |--------------------------------------------------------------------------
    | Webhook Signing Secret
    |--------------------------------------------------------------------------
    |
    | The shared secret used to verify incoming webhook signatures via HMAC-SHA256.
    | The payment provider must send a matching X-Webhook-Signature header.
    | Set to null to disable signature verification (not recommended in production).
    |
    */

    'signing_secret' => env('WEBHOOK_SIGNING_SECRET'),

];

