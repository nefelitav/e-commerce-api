<?php

namespace App\Repositories\Cart;

use App\Dto\Cart\Cart;
use App\Dto\Cart\UnpersistedCart;
use App\Exceptions\CartNotFoundException;
use App\Models\Cart\CartItemModel;
use App\Models\Cart\CartModel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class CartRepository
{
    /**
     * @return array<Cart>
     */
    public function getAll(): array
    {
        /** @var Collection<int, CartModel> $carts */
        $carts = CartModel::with('items')->get();

        return $carts->map(fn (CartModel $model) => Cart::fromModel($model))->all();
    }

    public function findById(int $id): ?Cart
    {
        /** @var CartModel|null $cart */
        $cart = CartModel::with('items')->find($id);

        return $cart ? Cart::fromModel($cart) : null;
    }

    public function findByUserId(int $userId): ?Cart
    {
        /** @var CartModel|null $cart */
        $cart = CartModel::with('items')->where('user_id', $userId)->first();

        return $cart ? Cart::fromModel($cart) : null;
    }

    public function persist(UnpersistedCart $unpersistedCart): Cart
    {
        /** @var Cart $created */
        $created = DB::transaction(function () use ($unpersistedCart) {
            /** @var CartModel $cartModel */
            $cartModel = CartModel::create($unpersistedCart->toArray());

            foreach ($unpersistedCart->items as $item) {
                CartItemModel::create($item->toArray($cartModel->id));
            }

            $cartModel->load('items');

            return Cart::fromModel($cartModel);
        });

        return $created;
    }

    /**
     * @throws CartNotFoundException
     */
    public function update(int $id, UnpersistedCart $unpersistedCart): Cart
    {
        /** @var Cart $updated */
        $updated = DB::transaction(function () use ($id, $unpersistedCart) {
            /** @var CartModel|null $cartModel */
            $cartModel = CartModel::query()->where('id', $id)->first();

            if (!$cartModel) {
                throw new CartNotFoundException($id);
            }

            $cartModel->update($unpersistedCart->toArray());

            if (!empty($unpersistedCart->items)) {
                CartItemModel::query()->where('cart_id', $cartModel->id)->delete();
                foreach ($unpersistedCart->items as $item) {
                    CartItemModel::create($item->toArray($cartModel->id));
                }
            }

            $cartModel->load('items');

            return Cart::fromModel($cartModel);
        });

        return $updated;
    }

    /**
     * @throws CartNotFoundException
     */
    public function delete(int $id): bool
    {
        /** @var CartModel|null $cartModel */
        $cartModel = CartModel::query()->where('id', $id)->first();

        if (!$cartModel) {
            throw new CartNotFoundException($id);
        }

        CartItemModel::query()->where('cart_id', $cartModel->id)->delete();

        return $cartModel->delete();
    }
}
