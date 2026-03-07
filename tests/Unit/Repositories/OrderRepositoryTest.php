<?php

namespace Tests\Unit\Repositories;

use App\Dto\Order\UnpersistedOrder;
use App\Dto\Order\UnpersistedOrderItem;
use App\Enums\OrderStatus;
use App\Exceptions\OrderNotFoundException;
use App\Models\Order\OrderItemModel;
use App\Models\Order\OrderModel;
use App\Models\Product\ProductModel;
use App\Models\UserModel;
use App\Repositories\Order\OrderRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private OrderRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = new OrderRepository;
    }

    public function test_it_returns_all_orders(): void
    {
        OrderModel::factory()->count(3)->create();

        $orders = $this->repository->getAll();

        $this->assertCount(3, $orders);
        $this->assertEquals(
            OrderModel::query()->first()->status,
            $orders[0]->status,
        );
    }

    public function test_it_finds_order_by_id_including_items(): void
    {
        $order = OrderModel::factory()->create();
        $item = OrderItemModel::factory()->create([
            'order_id' => $order->id,
        ]);

        $result = $this->repository->findById($order->id);

        $this->assertNotNull($result);
        $this->assertEquals($order->id, $result->id);
        $this->assertCount(1, $result->items);
        $this->assertEquals($item->product_id, $result->items[0]->productId);
    }

    public function test_it_returns_null_when_order_by_id_not_found(): void
    {
        $result = $this->repository->findById(999);

        $this->assertNull($result);
    }

    public function test_it_persists_a_new_order_with_items(): void
    {
        $user = UserModel::factory()->create();
        $product = ProductModel::factory()->create();

        $dto = new UnpersistedOrder(
            userId: $user->id,
            status: OrderStatus::Pending,
            totalPrice: 1999,
            items: [
                new UnpersistedOrderItem(
                    productId: $product->id,
                    quantity: 1,
                    unitPrice: 1999,
                ),
            ],
        );

        $result = $this->repository->persist($dto);

        $this->assertDatabaseHas('orders', [
            'id' => $result->id,
            'user_id' => $user->id,
            'status' => OrderStatus::Pending->value,
            'total_price' => 1999,
        ]);

        $this->assertDatabaseHas('order_items', [
            'order_id' => $result->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 1999,
        ]);
    }

    public function test_it_updates_an_existing_order_and_replaces_items(): void
    {
        $user = UserModel::factory()->create();
        $order = OrderModel::factory()->create([
            'user_id' => $user->id,
            'status' => OrderStatus::Pending->value,
            'total_price' => 100,
        ]);

        $oldItem = OrderItemModel::factory()->create([
            'order_id' => $order->id,
            'quantity' => 1,
            'unit_price' => 100,
        ]);

        $newProduct = ProductModel::factory()->create();

        $dto = new UnpersistedOrder(
            userId: $user->id,
            status: OrderStatus::Paid,
            totalPrice: 200,
            items: [
                new UnpersistedOrderItem(
                    productId: $newProduct->id,
                    quantity: 2,
                    unitPrice: 100,
                ),
            ],
        );

        $result = $this->repository->update($order->id, $dto);

        $this->assertEquals(OrderStatus::Paid, $result->status);
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => OrderStatus::Paid->value,
            'total_price' => 200,
        ]);

        $this->assertDatabaseMissing('order_items', [
            'id' => $oldItem->id,
        ]);

        $this->assertDatabaseHas('order_items', [
            'order_id' => $order->id,
            'product_id' => $newProduct->id,
            'quantity' => 2,
            'unit_price' => 100,
        ]);
    }

    public function test_it_throws_exception_when_updating_non_existing_order(): void
    {
        $this->expectException(OrderNotFoundException::class);

        $user = UserModel::factory()->create();

        $dto = new UnpersistedOrder(
            userId: $user->id,
            status: OrderStatus::Paid,
            totalPrice: 200,
            items: [],
        );

        $this->repository->update(999, $dto);
    }

    public function test_it_deletes_an_existing_order_and_its_items(): void
    {
        $order = OrderModel::factory()->create();
        OrderItemModel::factory()->create([
            'order_id' => $order->id,
        ]);

        $result = $this->repository->delete($order->id);

        $this->assertTrue($result);
        $this->assertDatabaseMissing('orders', [
            'id' => $order->id,
        ]);
        $this->assertDatabaseMissing('order_items', [
            'order_id' => $order->id,
        ]);
    }

    public function test_it_throws_exception_when_deleting_non_existing_order(): void
    {
        $this->expectException(OrderNotFoundException::class);

        $this->repository->delete(999);
    }
}
