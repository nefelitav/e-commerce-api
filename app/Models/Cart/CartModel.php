<?php

namespace App\Models\Cart;

use App\Models\CreatedAtUtcTrait;
use App\Models\UpdatedAtUtcTrait;
use App\Models\UserModel;
use Database\Factories\Cart\CartModelFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $user_id
 * @property UserModel $user
 * @property \Illuminate\Database\Eloquent\Collection<int, CartItemModel> $items
 * @method static static create(array<mixed> $attributes = [])
 */
class CartModel extends Model
{
    use CreatedAtUtcTrait;
    use UpdatedAtUtcTrait;
    /** @use HasFactory<CartModelFactory> */
    use HasFactory;

    protected $table = 'carts';

    protected $fillable = [
        'user_id',
    ];

    /**
     * @return BelongsTo<UserModel, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(UserModel::class, 'user_id');
    }

    /**
     * @return HasMany<CartItemModel, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(CartItemModel::class, 'cart_id');
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getUserId(): int
    {
        return $this->user_id;
    }
}
