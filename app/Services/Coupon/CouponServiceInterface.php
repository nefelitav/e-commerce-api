<?php

namespace App\Services\Coupon;

use App\Dto\Coupon\Coupon;
use App\Dto\Coupon\UnpersistedCoupon;
use App\Exceptions\CouponNotFoundException;
use App\Exceptions\InvalidCouponException;
use Illuminate\Pagination\LengthAwarePaginator;

interface CouponServiceInterface
{
    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Coupon>
     */
    public function listCoupons(
        int $page = 1,
        int $perPage = 15,
        string $sort = 'id',
        string $order = 'asc',
        array $filters = [],
    ): LengthAwarePaginator;

    public function getCouponById(int $id): ?Coupon;

    /**
     * @throws InvalidCouponException
     */
    public function createCoupon(UnpersistedCoupon $coupon): Coupon;

    /**
     * @throws CouponNotFoundException
     */
    public function updateCoupon(int $id, UnpersistedCoupon $coupon): Coupon;

    /**
     * @throws CouponNotFoundException
     */
    public function deleteCoupon(int $id): bool;

    /**
     * @throws CouponNotFoundException
     * @throws InvalidCouponException
     */
    public function validateCoupon(string $code, float $orderTotal): Coupon;

    public function calculateDiscount(Coupon $coupon, float $orderTotal): float;
}
