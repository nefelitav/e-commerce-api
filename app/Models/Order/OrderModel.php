<?php

namespace App\Models\Order;

use App\Enums\OrderStatus;
use App\Models\Coupon\CouponModel;
use App\Models\CreatedAtUtcTrait;
use App\Models\UpdatedAtUtcTrait;
use App\Models\UserModel;
use Database\Factories\Order\OrderModelFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property OrderStatus $status
 * @property float $total_price
 * @property int|null $coupon_id
 * @property float $discount_amount
 * @property Carbon|null $created_at
 * @property UserModel $user
 * @property CouponModel|null $coupon
 * @property Collection<int, OrderItemModel> $items
 *
 * @method static static create(array<mixed> $attributes = [])
 * @method static static|null find(int|string $id, array<int, string> $columns = ['*'])
 */
class OrderModel extends Model
{
    use CreatedAtUtcTrait;
    /** @use HasFactory<OrderModelFactory> */
    use HasFactory;
    use UpdatedAtUtcTrait;

    protected $table = 'orders';

    protected $fillable = [
        'user_id',
        'status',
        'total_price',
        'coupon_id',
        'discount_amount',
    ];

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
        ];
    }

    /**
     * @return BelongsTo<UserModel, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(UserModel::class, 'user_id');
    }

    /**
     * @return HasMany<OrderItemModel, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItemModel::class, 'order_id');
    }

    /**
     * @return BelongsTo<CouponModel, $this>
     */
    public function coupon(): BelongsTo
    {
        return $this->belongsTo(CouponModel::class, 'coupon_id');
    }
}
