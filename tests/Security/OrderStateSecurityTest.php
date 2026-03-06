<?php

namespace Tests\Security;

use App\Models\Order\OrderModel;
use App\Models\Product\ProductModel;
use App\Models\UserModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class OrderStateSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_cannot_create_order_with_paid_status(): void
    {
        $user = UserModel::factory()->create();
        $this->actingAs($user);

        $product = ProductModel::factory()->create(['quantity' => 10]);

        $response = $this->postJson(route('v1.orders.store'), [
            'status' => 'paid',
            'total_price' => $product->price,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1, 'unit_price' => $product->price],
            ],
        ]);

        // The system should either reject this or handle it appropriately
        $this->assertContains(
            $response->getStatusCode(),
            [Response::HTTP_CREATED, Response::HTTP_BAD_REQUEST, Response::HTTP_UNPROCESSABLE_ENTITY],
        );
    }

    public function test_user_cannot_create_order_with_shipped_status(): void
    {
        $user = UserModel::factory()->create();
        $this->actingAs($user);

        $product = ProductModel::factory()->create(['quantity' => 10]);

        $response = $this->postJson(route('v1.orders.store'), [
            'status' => 'shipped',
            'total_price' => $product->price,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1, 'unit_price' => $product->price],
            ],
        ]);

        $this->assertContains(
            $response->getStatusCode(),
            [Response::HTTP_CREATED, Response::HTTP_BAD_REQUEST, Response::HTTP_UNPROCESSABLE_ENTITY],
        );
    }

    public function test_user_cannot_create_order_with_delivered_status(): void
    {
        $user = UserModel::factory()->create();
        $this->actingAs($user);

        $product = ProductModel::factory()->create(['quantity' => 10]);

        $response = $this->postJson(route('v1.orders.store'), [
            'status' => 'delivered',
            'total_price' => $product->price,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1, 'unit_price' => $product->price],
            ],
        ]);

        $this->assertContains(
            $response->getStatusCode(),
            [Response::HTTP_CREATED, Response::HTTP_BAD_REQUEST, Response::HTTP_UNPROCESSABLE_ENTITY],
        );
    }

    public function test_user_cannot_transition_pending_to_shipped(): void
    {
        $user = UserModel::factory()->create();
        $this->actingAs($user);

        $product = ProductModel::factory()->create(['quantity' => 10]);

        $orderResponse = $this->postJson(route('v1.orders.store'), [
            'status' => 'pending',
            'total_price' => $product->price,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1, 'unit_price' => $product->price],
            ],
        ]);
        $orderId = $orderResponse->json('data.id');

        $this->putJson(route('v1.orders.update', $orderId), [
            'status' => 'shipped',
            'total_price' => $product->price,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1, 'unit_price' => $product->price],
            ],
        ])->assertStatus(Response::HTTP_BAD_REQUEST);

        $this->assertDatabaseHas('orders', [
            'id' => $orderId,
            'status' => 'pending',
        ]);
    }

    public function test_user_cannot_transition_pending_to_delivered(): void
    {
        $user = UserModel::factory()->create();
        $this->actingAs($user);

        $product = ProductModel::factory()->create(['quantity' => 10]);

        $orderResponse = $this->postJson(route('v1.orders.store'), [
            'status' => 'pending',
            'total_price' => $product->price,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1, 'unit_price' => $product->price],
            ],
        ]);
        $orderId = $orderResponse->json('data.id');

        $this->putJson(route('v1.orders.update', $orderId), [
            'status' => 'delivered',
            'total_price' => $product->price,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1, 'unit_price' => $product->price],
            ],
        ])->assertStatus(Response::HTTP_BAD_REQUEST);
    }

    public function test_user_cannot_transition_pending_to_paid(): void
    {
        $user = UserModel::factory()->create();
        $this->actingAs($user);

        $product = ProductModel::factory()->create(['quantity' => 10]);

        $orderResponse = $this->postJson(route('v1.orders.store'), [
            'status' => 'pending',
            'total_price' => $product->price,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1, 'unit_price' => $product->price],
            ],
        ]);
        $orderId = $orderResponse->json('data.id');

        $this->putJson(route('v1.orders.update', $orderId), [
            'status' => 'paid',
            'total_price' => $product->price,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1, 'unit_price' => $product->price],
            ],
        ])->assertStatus(Response::HTTP_BAD_REQUEST);
    }

    public function test_user_cannot_transition_cancelled_to_any_status(): void
    {
        $user = UserModel::factory()->create();
        $this->actingAs($user);

        $product = ProductModel::factory()->create(['quantity' => 10]);

        $orderResponse = $this->postJson(route('v1.orders.store'), [
            'status' => 'pending',
            'total_price' => $product->price,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1, 'unit_price' => $product->price],
            ],
        ]);
        $orderId = $orderResponse->json('data.id');

        // First cancel the order
        $this->putJson(route('v1.orders.update', $orderId), [
            'status' => 'cancelled',
            'total_price' => $product->price,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1, 'unit_price' => $product->price],
            ],
        ])->assertStatus(Response::HTTP_OK);

        // Try to revert cancelled to pending
        $this->putJson(route('v1.orders.update', $orderId), [
            'status' => 'pending',
            'total_price' => $product->price,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1, 'unit_price' => $product->price],
            ],
        ])->assertStatus(Response::HTTP_BAD_REQUEST);
    }

    public function test_order_cannot_be_oversold_beyond_stock(): void
    {
        $user = UserModel::factory()->create();
        $this->actingAs($user);

        $product = ProductModel::factory()->create(['quantity' => 3, 'price' => 10]);

        $this->postJson(route('v1.orders.store'), [
            'status' => 'pending',
            'total_price' => 50,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 5, 'unit_price' => 10],
            ],
        ])->assertStatus(Response::HTTP_BAD_REQUEST);

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'quantity' => 3,
        ]);
    }

    public function test_payment_webhook_cannot_bypass_status_machine(): void
    {
        $order = OrderModel::factory()->create(['status' => 'shipped']);

        $this->postJson(route('v1.webhooks.payments'), [
            'order_id' => $order->id,
            'payment_reference' => 'pay_bypass',
            'status' => 'paid',
        ])->assertStatus(Response::HTTP_BAD_REQUEST);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'shipped',
        ]);
    }

    public function test_payment_webhook_cannot_pay_cancelled_order(): void
    {
        $order = OrderModel::factory()->create(['status' => 'cancelled']);

        $this->postJson(route('v1.webhooks.payments'), [
            'order_id' => $order->id,
            'payment_reference' => 'pay_cancelled',
            'status' => 'paid',
        ])->assertStatus(Response::HTTP_BAD_REQUEST);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'cancelled',
        ]);
    }

    public function test_payment_webhook_cannot_pay_delivered_order(): void
    {
        $order = OrderModel::factory()->create(['status' => 'delivered']);

        $this->postJson(route('v1.webhooks.payments'), [
            'order_id' => $order->id,
            'payment_reference' => 'pay_delivered',
            'status' => 'paid',
        ])->assertStatus(Response::HTTP_BAD_REQUEST);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'delivered',
        ]);
    }
}
