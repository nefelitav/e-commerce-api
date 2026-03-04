<?php

namespace App\Models\Order;

use App\Models\CreatedAtUtcTrait;
use App\Models\Product\ProductModel;
use App\Models\UpdatedAtUtcTrait;
use Database\Factories\Order\OrderItemModelFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $order_id
 * @property int $product_id
 * @property int $quantity
 * @property float $unit_price
 * @property OrderModel $order
 * @property ProductModel $product
 * @method static static create(array<mixed> $attributes = [])
 * @method static static|null find(int|string $id, array<int, string> $columns = ['*'])
 */
class OrderItemModel extends Model
{
    use CreatedAtUtcTrait;
    use UpdatedAtUtcTrait;
    /** @use HasFactory<OrderItemModelFactory> */
    use HasFactory;

    protected $table = 'order_items';

    protected $fillable = [
        'order_id',
        'product_id',
        'quantity',
        'unit_price',
    ];

    /**
     * @return BelongsTo<OrderModel, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(OrderModel::class, 'order_id');
    }

    /**
     * @return BelongsTo<ProductModel, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(ProductModel::class, 'product_id');
    }
}

