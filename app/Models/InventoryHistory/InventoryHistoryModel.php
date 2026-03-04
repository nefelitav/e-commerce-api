<?php

namespace App\Models\InventoryHistory;

use App\Models\CreatedAtUtcTrait;
use App\Models\Product\ProductModel;
use App\Models\UpdatedAtUtcTrait;
use Database\Factories\InventoryHistory\InventoryHistoryModelFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $product_id
 * @property string $change_type
 * @property int $quantity_changed
 * @property int $previous_quantity
 * @property int $new_quantity
 * @property ProductModel $product
 * @method static static create(array<mixed> $attributes = [])
 * @method static static|null find(int|string $id, array<int, string> $columns = ['*'])
 */
class InventoryHistoryModel extends Model
{
    use CreatedAtUtcTrait;
    use UpdatedAtUtcTrait;
    /** @use HasFactory<InventoryHistoryModelFactory> */
    use HasFactory;

    protected $table = 'inventory_history';

    protected $fillable = [
        'product_id',
        'change_type',
        'quantity_changed',
        'previous_quantity',
        'new_quantity',
    ];

    /**
     * @return BelongsTo<ProductModel, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(ProductModel::class, 'product_id');
    }
}

