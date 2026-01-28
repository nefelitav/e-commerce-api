<?php

namespace App\Models\Order;

use App\Models\CreatedAtUtcTrait;
use App\Models\UpdatedAtUtcTrait;
use App\Models\UserModel;
use Database\Factories\Order\OrderModelFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Collection;

/**
 * @property int $id
 * @property int $user_id
 * @property string $status
 * @property float $total_price
 * @property UserModel $user
 * @property Collection<int, OrderItemModel> $items
 * @method static static create(array<mixed> $attributes = [])
 */
class OrderModel extends Model
{
    use CreatedAtUtcTrait;
    use UpdatedAtUtcTrait;
    /** @use HasFactory<OrderModelFactory> */
    use HasFactory;

    protected $table = 'orders';

    protected $fillable = [
        'user_id',
        'status',
        'total_price',
    ];

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
}

