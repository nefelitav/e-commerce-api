<?php

namespace Tests\Unit\Events;

use App\Dto\Order\Order;
use App\Dto\Order\OrderItem;
use App\Enums\OrderStatus;
use App\Events\OrderPaidEvent;
use Tests\TestCase;

class OrderPaidEventTest extends TestCase
{
    public function test_to_payload_returns_correct_structure(): void
    {
        $order = new Order(
            id: 42,
            userId: 7,
            status: OrderStatus::Paid,
            totalPrice: 299.99,
            createdAt: '2026-03-04T10:00:00+00:00',
            items: [
                new OrderItem(id: 1, orderId: 42, productId: 10, quantity: 2, unitPrice: 99.99),
                new OrderItem(id: 2, orderId: 42, productId: 20, quantity: 1, unitPrice: 100.01),
            ],
        );

        $event = new OrderPaidEvent($order, '2026-03-04T10:30:00+00:00');
        $payload = $event->toPayload();

        $this->assertEquals('order.paid', $payload['event']);
        $this->assertEquals('2026-03-04T10:30:00+00:00', $payload['occurred_at']);
        $this->assertEquals(42, $payload['data']['order_id']);
        $this->assertEquals(7, $payload['data']['user_id']);
        $this->assertEquals(OrderStatus::Paid->value, $payload['data']['status']);
        $this->assertEquals(299.99, $payload['data']['total_price']);
        $this->assertCount(2, $payload['data']['items']);
        $this->assertEquals(10, $payload['data']['items'][0]['product_id']);
        $this->assertEquals(2, $payload['data']['items'][0]['quantity']);
        $this->assertEquals(99.99, $payload['data']['items'][0]['unit_price']);
    }

    public function test_to_payload_with_empty_items(): void
    {
        $order = new Order(
            id: 1,
            userId: 1,
            status: OrderStatus::Paid,
            totalPrice: 0,
            createdAt: '2026-03-04T10:00:00+00:00',
            items: [],
        );

        $event = new OrderPaidEvent($order, '2026-03-04T10:00:00+00:00');
        $payload = $event->toPayload();

        $this->assertEmpty($payload['data']['items']);
    }
}
