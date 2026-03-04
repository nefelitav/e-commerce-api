<?php

namespace App\Listeners;

use App\Events\OrderPaidEvent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class SendOrderPaidWebhook implements ShouldQueue
{
    public string $queue = 'webhooks';

    public int $tries = 3;

    public int $backoff = 10;

    public function handle(OrderPaidEvent $event): void
    {
        /** @var string|null $url */
        $url = config('webhooks.order_paid_url');

        if ($url === null || $url === '') {
            return;
        }

        $payload = $event->toPayload();

        $response = Http::timeout(10)->post($url, $payload);

        if ($response->failed()) {
            Log::channel('audit')->warning('order.paid webhook failed', [
                'order_id' => $event->order->id,
                'url' => $url,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            $response->throw();
        }

        Log::channel('audit')->info('order.paid webhook sent', [
            'order_id' => $event->order->id,
            'url' => $url,
            'status' => $response->status(),
        ]);
    }
}

