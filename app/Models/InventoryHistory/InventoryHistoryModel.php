<?php

namespace App\Models\InventoryHistory;

use App\Models\CreatedAtUtcTrait;
use App\Models\Product\ProductModel;
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
 * @property \Carbon\Carbon|null $created_at
 * @property ProductModel $product
 * @method static static create(array<mixed> $attributes = [])
 */
class InventoryHistoryModel extends Model
{
    use CreatedAtUtcTrait;
    /** @use HasFactory<InventoryHistoryModelFactory> */
    use HasFactory;

    protected $table = 'inventory_history';

    public $timestamps = false;

    protected $fillable = [
        'product_id',
        'change_type',
        'quantity_changed',
        'previous_quantity',
        'new_quantity',
        'created_at',
    ];

    /**
     * @return BelongsTo<ProductModel, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(ProductModel::class, 'product_id');
    }
}

