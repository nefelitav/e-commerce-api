<?php

namespace App\Services\Coupon;

use App\Dto\Coupon\Coupon;
use App\Dto\Coupon\UnpersistedCoupon;
use App\Enums\CouponType;
use App\Exceptions\CouponNotFoundException;
use App\Exceptions\InvalidCouponException;
use App\Repositories\Coupon\CouponRepositoryInterface;
use App\Services\AuditLogger;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;

final readonly class CouponService implements CouponServiceInterface
{
    public function __construct(
        private CouponRepositoryInterface $repository,
        private AuditLogger $auditLogger,
    ) {}

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
    ): LengthAwarePaginator {
        return $this->repository->getAll($page, $perPage, $sort, $order, $filters);
    }

    public function getCouponById(int $id): ?Coupon
    {
        return $this->repository->findById($id);
    }

    /**
     * @throws InvalidCouponException
     */
    public function createCoupon(UnpersistedCoupon $coupon): Coupon
    {
        $existing = $this->repository->findByCode($coupon->code);

        if ($existing !== null) {
            throw new InvalidCouponException("A coupon with code '{$coupon->code}' already exists.");
        }

        $created = $this->repository->persist($coupon);

        $this->auditLogger->log('coupon.created', 'coupon', $created->id, [
            'code' => $created->code,
            'type' => $created->type->value,
            'value' => $created->value,
        ]);

        return $created;
    }

    /**
     * @throws CouponNotFoundException
     */
    public function updateCoupon(int $id, UnpersistedCoupon $coupon): Coupon
    {
        $updated = $this->repository->update($id, $coupon);

        $this->auditLogger->log('coupon.updated', 'coupon', $id, [
            'code' => $updated->code,
            'type' => $updated->type->value,
            'value' => $updated->value,
        ]);

        return $updated;
    }

    /**
     * @throws CouponNotFoundException
     */
    public function deleteCoupon(int $id): bool
    {
        $result = $this->repository->delete($id);

        $this->auditLogger->log('coupon.deleted', 'coupon', $id);

        return $result;
    }

    /**
     * @throws CouponNotFoundException
     * @throws InvalidCouponException
     */
    public function validateCoupon(string $code, float $orderTotal): Coupon
    {
        $coupon = $this->repository->findByCode($code);

        if ($coupon === null) {
            throw new CouponNotFoundException($code);
        }

        if (! $coupon->isActive) {
            throw new InvalidCouponException("Coupon '{$code}' is not active.");
        }

        if ($coupon->expiresAt !== null && Carbon::parse($coupon->expiresAt)->isPast()) {
            throw new InvalidCouponException("Coupon '{$code}' has expired.");
        }

        if ($coupon->maxUses !== null && $coupon->timesUsed >= $coupon->maxUses) {
            throw new InvalidCouponException("Coupon '{$code}' has reached its maximum number of uses.");
        }

        if ($coupon->minOrderAmount !== null && $orderTotal < $coupon->minOrderAmount) {
            throw new InvalidCouponException(
                "Order total must be at least {$coupon->minOrderAmount} to use coupon '{$code}'.",
            );
        }

        return $coupon;
    }

    public function calculateDiscount(Coupon $coupon, float $orderTotal): float
    {
        return match ($coupon->type) {
            CouponType::Percentage => round($orderTotal * ($coupon->value / 100), 2),
            CouponType::FixedAmount => min($coupon->value, $orderTotal),
        };
    }
}
