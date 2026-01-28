<?php

namespace Tests\Unit\Services;

use App\Dto\Cart\Cart;
use App\Dto\Cart\UnpersistedCart;
use App\Exceptions\CartNotFoundException;
use App\Models\Cart\CartModel;
use App\Models\UserModel;
use App\Repositories\Cart\CartRepository;
use App\Services\Cart\CartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CartServiceTest extends TestCase
{
    use RefreshDatabase;

    /** @var CartRepository&\PHPUnit\Framework\MockObject\MockObject $repository */
    private CartRepository $repository;
    private CartService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = $this->createMock(CartRepository::class);
        $this->service = new CartService($this->repository);
    }

    public function test_listCarts_returns_array_of_carts(): void
    {
        $carts = [Cart::fromModel(CartModel::factory()->create())];

        $this->repository
            ->expects($this->once())
            ->method('getAll')
            ->willReturn($carts);

        $result = $this->service->listCarts();

        $this->assertSame($carts, $result);
    }

    public function test_getCartById_returns_cart(): void
    {
        $id = 1;
        $cart = Cart::fromModel(CartModel::factory()->create());

        $this->repository
            ->expects($this->once())
            ->method('findById')
            ->with($id)
            ->willReturn($cart);

        $result = $this->service->getCartById($id);

        $this->assertSame($cart, $result);
    }

    public function test_getCartById_returns_null(): void
    {
        $id = 1;

        $this->repository
            ->expects($this->once())
            ->method('findById')
            ->with($id)
            ->willReturn(null);

        $result = $this->service->getCartById($id);

        $this->assertNull($result);
    }

    public function test_getCartByUserId_returns_cart(): void
    {
        $userId = 1;
        $cart = Cart::fromModel(CartModel::factory()->create());

        $this->repository
            ->expects($this->once())
            ->method('findByUserId')
            ->with($userId)
            ->willReturn($cart);

        $result = $this->service->getCartByUserId($userId);

        $this->assertSame($cart, $result);
    }

    public function test_createCart_persists_and_returns_cart(): void
    {
        $user = UserModel::factory()->create();
        $unpersisted = new UnpersistedCart(
            userId: $user->id,
            items: [],
        );

        $persisted = Cart::fromModel(CartModel::factory()->create());

        $this->repository
            ->expects($this->once())
            ->method('persist')
            ->with($unpersisted)
            ->willReturn($persisted);

        $result = $this->service->createCart($unpersisted);

        $this->assertEquals($persisted, $result);
    }

    public function test_updateCart_calls_repository_update(): void
    {
        $id = 1;
        $user = UserModel::factory()->create();
        $unpersisted = new UnpersistedCart(
            userId: $user->id,
            items: [],
        );
        $updated = Cart::fromModel(CartModel::factory()->create());

        $this->repository
            ->expects($this->once())
            ->method('update')
            ->with($id, $unpersisted)
            ->willReturn($updated);

        $result = $this->service->updateCart($id, $unpersisted);

        $this->assertSame($updated, $result);
    }

    public function test_updateCart_throws_CartNotFoundException(): void
    {
        $id = 1;
        $user = UserModel::factory()->create();
        $unpersisted = new UnpersistedCart(
            userId: $user->id,
            items: [],
        );

        $this->repository
            ->expects($this->once())
            ->method('update')
            ->with($id, $unpersisted)
            ->willThrowException(new CartNotFoundException($id));

        $this->expectException(CartNotFoundException::class);

        $this->service->updateCart($id, $unpersisted);
    }

    public function test_deleteCart_returns_true(): void
    {
        $id = 1;

        $this->repository
            ->expects($this->once())
            ->method('delete')
            ->with($id)
            ->willReturn(true);

        $result = $this->service->deleteCart($id);

        $this->assertTrue($result);
    }

    public function test_deleteCart_throws_CartNotFoundException(): void
    {
        $id = 1;

        $this->repository
            ->expects($this->once())
            ->method('delete')
            ->with($id)
            ->willThrowException(new CartNotFoundException($id));

        $this->expectException(CartNotFoundException::class);

        $this->service->deleteCart($id);
    }
}
