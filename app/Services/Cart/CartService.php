<?php

namespace App\Services\Cart;

use App\Dto\Cart\Cart;
use App\Dto\Cart\UnpersistedCart;
use App\Exceptions\CartNotFoundException;
use App\Repositories\Cart\CartRepository;

final readonly class CartService
{
    public function __construct(
        private CartRepository $repository,
    ) {
    }

    /**
     * @return array<Cart>
     */
    public function listCarts(): array
    {
        return $this->repository->getAll();
    }

    public function getCartById(int $id): ?Cart
    {
        return $this->repository->findById($id);
    }

    public function getCartByUserId(int $userId): ?Cart
    {
        return $this->repository->findByUserId($userId);
    }

    public function createCart(UnpersistedCart $unpersistedCart): Cart
    {
        return $this->repository->persist($unpersistedCart);
    }

    /**
     * @throws CartNotFoundException
     */
    public function updateCart(int $id, UnpersistedCart $unpersistedCart): Cart
    {
        return $this->repository->update($id, $unpersistedCart);
    }

    /**
     * @throws CartNotFoundException
     */
    public function deleteCart(int $id): bool
    {
        return $this->repository->delete($id);
    }
}
