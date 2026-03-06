<?php

namespace App\Models\Coupon;

use App\Enums\CouponType;
use App\Models\CreatedAtUtcTrait;
use App\Models\UpdatedAtUtcTrait;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $code
 * @property CouponType $type
 * @property float $value
 * @property float|null $min_order_amount
 * @property int|null $max_uses
 * @property int $times_used
 * @property Carbon|null $expires_at
 * @property bool $is_active
 *
 * @method static static create(array<mixed> $attributes = [])
 * @method static Builder<CouponModel> query()
 * @method static static|null find(int|string $id, array<int, string> $columns = ['*'])
 */
class CouponModel extends Model
{
    use CreatedAtUtcTrait;
    use UpdatedAtUtcTrait;

    protected $table = 'coupons';

    protected $fillable = [
        'code',
        'type',
        'value',
        'min_order_amount',
        'max_uses',
        'times_used',
        'expires_at',
        'is_active',
    ];

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'type' => CouponType::class,
            'is_active' => 'boolean',
            'expires_at' => 'datetime',
        ];
    }
}
