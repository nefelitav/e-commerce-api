<?php

namespace Tests\Unit\Listeners;

use App\Dto\Order\Order;
use App\Dto\Order\OrderItem;
use App\Enums\OrderStatus;
use App\Events\OrderPaidEvent;
use App\Listeners\SendOrderPaidWebhook;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class SendOrderPaidWebhookTest extends TestCase
{
    private function makeEvent(): OrderPaidEvent
    {
        $order = new Order(
            id: 42,
            userId: 7,
            status: OrderStatus::Paid,
            totalPrice: 299.99,
            createdAt: '2026-03-04T10:00:00+00:00',
            items: [
                new OrderItem(id: 1, orderId: 42, productId: 10, quantity: 2, unitPrice: 99.99),
            ],
        );

        return new OrderPaidEvent($order, '2026-03-04T10:30:00+00:00');
    }

    public function test_sends_post_request_to_configured_url(): void
    {
        Http::fake([
            'https://example.com/webhook' => Http::response([], 200),
        ]);

        config(['webhooks.order_paid_url' => 'https://example.com/webhook']);

        $listener = new SendOrderPaidWebhook();
        $listener->handle($this->makeEvent());

        Http::assertSent(function ($request) {
            return $request->url() === 'https://example.com/webhook'
                && $request['event'] === 'order.paid'
                && $request['data']['order_id'] === 42
                && $request['data']['total_price'] === 299.99
                && count($request['data']['items']) === 1;
        });
    }

    public function test_skips_when_url_is_not_configured(): void
    {
        Http::fake();

        config(['webhooks.order_paid_url' => null]);

        $listener = new SendOrderPaidWebhook();
        $listener->handle($this->makeEvent());

        Http::assertNothingSent();
    }

    public function test_skips_when_url_is_empty_string(): void
    {
        Http::fake();

        config(['webhooks.order_paid_url' => '']);

        $listener = new SendOrderPaidWebhook();
        $listener->handle($this->makeEvent());

        Http::assertNothingSent();
    }

    public function test_throws_on_failed_response_for_retry(): void
    {
        Http::fake([
            'https://example.com/webhook' => Http::response('Server Error', 500),
        ]);

        config(['webhooks.order_paid_url' => 'https://example.com/webhook']);

        Log::shouldReceive('channel')
            ->with('audit')
            ->once()
            ->andReturnSelf();

        Log::shouldReceive('warning')
            ->once()
            ->withArgs(function (string $message, array $context) {
                return $message === 'order.paid webhook failed'
                    && $context['order_id'] === 42
                    && $context['status'] === 500;
            });

        $this->expectException(\Illuminate\Http\Client\RequestException::class);

        $listener = new SendOrderPaidWebhook();
        $listener->handle($this->makeEvent());
    }

    public function test_logs_successful_delivery(): void
    {
        Http::fake([
            'https://example.com/webhook' => Http::response([], 200),
        ]);

        config(['webhooks.order_paid_url' => 'https://example.com/webhook']);

        Log::shouldReceive('channel')
            ->with('audit')
            ->once()
            ->andReturnSelf();

        Log::shouldReceive('info')
            ->once()
            ->withArgs(function (string $message, array $context) {
                return $message === 'order.paid webhook sent'
                    && $context['order_id'] === 42
                    && $context['status'] === 200;
            });

        $listener = new SendOrderPaidWebhook();
        $listener->handle($this->makeEvent());
    }

    public function test_listener_is_queued(): void
    {
        $listener = new SendOrderPaidWebhook();

        $this->assertInstanceOf(\Illuminate\Contracts\Queue\ShouldQueue::class, $listener);
        $this->assertEquals('webhooks', $listener->queue);
        $this->assertEquals(3, $listener->tries);
    }
}

