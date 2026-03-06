<?php

namespace App\Repositories\Coupon;

use App\Dto\Coupon\Coupon;
use App\Dto\Coupon\UnpersistedCoupon;
use App\Exceptions\CouponNotFoundException;
use App\Models\Coupon\CouponModel;
use Illuminate\Pagination\LengthAwarePaginator;

class CouponRepository implements CouponRepositoryInterface
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
    ): LengthAwarePaginator {
        $query = CouponModel::query();

        if (isset($filters['code'])) {
            $query->where('code', 'like', '%'.$filters['code'].'%');
        }
        if (isset($filters['type'])) {
            $query->where('type', $filters['type']);
        }
        if (isset($filters['is_active'])) {
            $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN));
        }

        $query->orderBy($sort, $order);

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        $items = $paginator->getCollection()->map(fn (CouponModel $model) => Coupon::fromModel($model));

        return new LengthAwarePaginator(
            $items,
            $paginator->total(),
            $paginator->perPage(),
            $paginator->currentPage(),
            ['path' => LengthAwarePaginator::resolveCurrentPath()],
        );
    }

    public function findById(int $id): ?Coupon
    {
        /** @var CouponModel|null $model */
        $model = CouponModel::find($id);

        return $model ? Coupon::fromModel($model) : null;
    }

    public function findByCode(string $code): ?Coupon
    {
        /** @var CouponModel|null $model */
        $model = CouponModel::query()->where('code', $code)->first();

        return $model ? Coupon::fromModel($model) : null;
    }

    public function persist(UnpersistedCoupon $coupon): Coupon
    {
        /** @var CouponModel $model */
        $model = CouponModel::create($coupon->toArray());

        return Coupon::fromModel($model);
    }

    /**
     * @throws CouponNotFoundException
     */
    public function update(int $id, UnpersistedCoupon $coupon): Coupon
    {
        /** @var CouponModel|null $model */
        $model = CouponModel::query()->where('id', $id)->first();

        if (! $model) {
            throw new CouponNotFoundException($id);
        }

        $model->update($coupon->toArray());
        $model->refresh();

        return Coupon::fromModel($model);
    }

    /**
     * @throws CouponNotFoundException
     */
    public function delete(int $id): bool
    {
        /** @var CouponModel|null $model */
        $model = CouponModel::query()->where('id', $id)->first();

        if (! $model) {
            throw new CouponNotFoundException($id);
        }

        return $model->delete();
    }

    /**
     * @throws CouponNotFoundException
     */
    public function incrementUsage(int $id): void
    {
        /** @var CouponModel|null $model */
        $model = CouponModel::query()->where('id', $id)->first();

        if (! $model) {
            throw new CouponNotFoundException($id);
        }

        $model->increment('times_used');
    }
}
