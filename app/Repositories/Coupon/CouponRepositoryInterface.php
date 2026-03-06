<?php

namespace App\Repositories\Coupon;

use App\Dto\Coupon\Coupon;
use App\Dto\Coupon\UnpersistedCoupon;
use App\Exceptions\CouponNotFoundException;
use Illuminate\Pagination\LengthAwarePaginator;

interface CouponRepositoryInterface
{
    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Coupon>
     */
    public function getAll(
        int $page = 1,
        int $perPage = 15,
        string $sort = 'id',
        string $order = 'asc',
        array $filters = [],
    ): LengthAwarePaginator;

    public function findById(int $id): ?Coupon;

    public function findByCode(string $code): ?Coupon;

    public function persist(UnpersistedCoupon $coupon): Coupon;

    /**
     * @throws CouponNotFoundException
     */
    public function update(int $id, UnpersistedCoupon $coupon): Coupon;

    /**
     * @throws CouponNotFoundException
     */
    public function delete(int $id): bool;

    /**
     * @throws CouponNotFoundException
     */
    public function incrementUsage(int $id): void;
}
