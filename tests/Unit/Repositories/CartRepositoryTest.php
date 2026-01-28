<?php

namespace Tests\Unit\Repositories;

use App\Dto\Cart\Cart;
use App\Dto\Cart\UnpersistedCart;
use App\Dto\Cart\UnpersistedCartItem;
use App\Exceptions\CartNotFoundException;
use App\Models\Cart\CartItemModel;
use App\Models\Cart\CartModel;
use App\Models\Product\ProductModel;
use App\Models\UserModel;
use App\Repositories\Cart\CartRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CartRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private CartRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = new CartRepository();
    }

    public function test_it_returns_all_carts(): void
    {
        CartModel::factory()->count(3)->create();

        $carts = $this->repository->getAll();

        $this->assertCount(3, $carts);
        $this->assertEquals(
            CartModel::query()->first()->user_id,
            $carts[0]->userId
        );
    }

    public function test_it_finds_cart_by_id_including_items(): void
    {
        $cart = CartModel::factory()->create();
        $item = CartItemModel::factory()->create([
            'cart_id' => $cart->id,
        ]);

        $result = $this->repository->findById($cart->id);

        $this->assertNotNull($result);
        $this->assertEquals($cart->id, $result->id);
        $this->assertCount(1, $result->items);
        $this->assertEquals($item->product_id, $result->items[0]->productId);
    }

    public function test_it_finds_cart_by_user_id(): void
    {
        $user = UserModel::factory()->create();
        $cart = CartModel::factory()->create([
            'user_id' => $user->id,
        ]);

        $result = $this->repository->findByUserId($user->id);

        $this->assertNotNull($result);
        $this->assertEquals($cart->id, $result->id);
        $this->assertEquals($user->id, $result->userId);
    }

    public function test_it_returns_null_when_cart_by_id_not_found(): void
    {
        $result = $this->repository->findById(999);

        $this->assertNull($result);
    }

    public function test_it_persists_a_new_cart_with_items(): void
    {
        $user = UserModel::factory()->create();
        $product = ProductModel::factory()->create();

        $dto = new UnpersistedCart(
            userId: $user->id,
            items: [
                new UnpersistedCartItem(
                    productId: $product->id,
                    quantity: 2,
                ),
            ],
        );

        $result = $this->repository->persist($dto);

        $this->assertDatabaseHas('carts', [
            'id' => $result->id,
            'user_id' => $user->id,
        ]);

        $this->assertDatabaseHas('cart_items', [
            'cart_id' => $result->id,
            'product_id' => $product->id,
            'quantity' => 2,
        ]);
    }

    public function test_it_updates_an_existing_cart_and_replaces_items(): void
    {
        $user = UserModel::factory()->create();
        $cart = CartModel::factory()->create([
            'user_id' => $user->id,
        ]);

        $oldItem = CartItemModel::factory()->create([
            'cart_id' => $cart->id,
            'quantity' => 1,
        ]);

        $newProduct = ProductModel::factory()->create();

        $dto = new UnpersistedCart(
            userId: $user->id,
            items: [
                new UnpersistedCartItem(
                    productId: $newProduct->id,
                    quantity: 3,
                ),
            ],
        );

        $result = $this->repository->update($cart->id, $dto);

        $this->assertEquals($user->id, $result->userId);
        $this->assertDatabaseHas('carts', [
            'id' => $cart->id,
            'user_id' => $user->id,
        ]);

        $this->assertDatabaseMissing('cart_items', [
            'id' => $oldItem->id,
        ]);

        $this->assertDatabaseHas('cart_items', [
            'cart_id' => $cart->id,
            'product_id' => $newProduct->id,
            'quantity' => 3,
        ]);
    }

    public function test_it_throws_exception_when_updating_non_existing_cart(): void
    {
        $this->expectException(CartNotFoundException::class);

        $user = UserModel::factory()->create();

        $dto = new UnpersistedCart(
            userId: $user->id,
            items: [],
        );

        $this->repository->update(999, $dto);
    }

    public function test_it_deletes_an_existing_cart_and_its_items(): void
    {
        $cart = CartModel::factory()->create();
        CartItemModel::factory()->create([
            'cart_id' => $cart->id,
        ]);

        $result = $this->repository->delete($cart->id);

        $this->assertTrue($result);
        $this->assertDatabaseMissing('carts', [
            'id' => $cart->id,
        ]);
        $this->assertDatabaseMissing('cart_items', [
            'cart_id' => $cart->id,
        ]);
    }

    public function test_it_throws_exception_when_deleting_non_existing_cart(): void
    {
        $this->expectException(CartNotFoundException::class);

        $this->repository->delete(999);
    }
}
