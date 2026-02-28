<?php

namespace Tests\Unit\Services;

use App\Dto\Order\Order;
use App\Dto\Order\UnpersistedOrder;
use App\Exceptions\OrderNotFoundException;
use App\Models\Order\OrderModel;
use App\Models\UserModel;
use App\Repositories\Order\OrderRepository;
use App\Services\Order\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\TestCase;

class OrderServiceTest extends TestCase
{
    use RefreshDatabase;

    /** @var OrderRepository&MockObject $repository */
    private OrderRepository $repository;
    private OrderService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = $this->createMock(OrderRepository::class);
        $this->service = new OrderService($this->repository);
    }

    public function test_listOrders_returns_paginator_of_orders(): void
    {
        $orderModel = OrderModel::factory()->create();
        $order = Order::fromModel($orderModel);

        $paginator = \Mockery::mock(LengthAwarePaginator::class);
        $paginator->shouldReceive('items')->andReturn([$order]);

        $this->repository
            ->expects($this->once())
            ->method('getAll')
            ->with(1, 15, 'id', 'asc', [], [])
            ->willReturn($paginator);

        $result = $this->service->listOrders(1, 15, 'id', 'asc', [], []);
    }

    public function test_getOrderById_returns_order(): void
    {
        $id = 1;
        $order = Order::fromModel(OrderModel::factory()->create());

        $this->repository
            ->expects($this->once())
            ->method('findById')
            ->with($id)
            ->willReturn($order);

        $result = $this->service->getOrderById($id);

        $this->assertSame($order, $result);
    }

    public function test_getOrderById_returns_null(): void
    {
        $id = 1;

        $this->repository
            ->expects($this->once())
            ->method('findById')
            ->with($id)
            ->willReturn(null);

        $result = $this->service->getOrderById($id);

        $this->assertNull($result);
    }

    public function test_createOrder_persists_and_returns_order(): void
    {
        $user = UserModel::factory()->create();
        $unpersisted = new UnpersistedOrder(
            userId: $user->id,
            status: 'pending',
            totalPrice: 100,
            items: [],
        );

        $persisted = Order::fromModel(OrderModel::factory()->create());

        $this->repository
            ->expects($this->once())
            ->method('persist')
            ->with($unpersisted)
            ->willReturn($persisted);

        $result = $this->service->createOrder($unpersisted);

        $this->assertEquals($persisted, $result);
    }

    public function test_updateOrder_calls_repository_update(): void
    {
        $id = 1;
        $user = UserModel::factory()->create();
        $unpersisted = new UnpersistedOrder(
            userId: $user->id,
            status: 'paid',
            totalPrice: 200,
            items: [],
        );
        $updated = Order::fromModel(OrderModel::factory()->create());

        $this->repository
            ->expects($this->once())
            ->method('update')
            ->with($id, $unpersisted)
            ->willReturn($updated);

        $result = $this->service->updateOrder($id, $unpersisted);

        $this->assertSame($updated, $result);
    }

    public function test_updateOrder_throws_OrderNotFoundException(): void
    {
        $id = 1;
        $user = UserModel::factory()->create();
        $unpersisted = new UnpersistedOrder(
            userId: $user->id,
            status: 'paid',
            totalPrice: 200,
            items: [],
        );

        $this->repository
            ->expects($this->once())
            ->method('update')
            ->with($id, $unpersisted)
            ->willThrowException(new OrderNotFoundException($id));

        $this->expectException(OrderNotFoundException::class);

        $this->service->updateOrder($id, $unpersisted);
    }

    public function test_deleteOrder_returns_true(): void
    {
        $id = 1;

        $this->repository
            ->expects($this->once())
            ->method('delete')
            ->with($id)
            ->willReturn(true);

        $result = $this->service->deleteOrder($id);

        $this->assertTrue($result);
    }

    public function test_deleteOrder_throws_OrderNotFoundException(): void
    {
        $id = 1;

        $this->repository
            ->expects($this->once())
            ->method('delete')
            ->with($id)
            ->willThrowException(new OrderNotFoundException($id));

        $this->expectException(OrderNotFoundException::class);

        $this->service->deleteOrder($id);
    }
}

