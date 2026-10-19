<?php

namespace App\Models\Product;

use App\Models\Category\CategoryModel;
use App\Models\CreatedAtUtcTrait;
use App\Models\UpdatedAtUtcTrait;
use Database\Factories\Product\ProductModelFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property float $price
 * @property int $quantity
 * @property int $version
 * @property int $category_id
 * @property CategoryModel $category
 *
 * @method static static create(array<mixed> $attributes = [])
 * @method static Builder<ProductModel> query()
 * @method static static|null find(int|string $id, array<int, string> $columns = ['*'])
 */
class ProductModel extends Model
{
    use CreatedAtUtcTrait;
    /** @use HasFactory<ProductModelFactory> */
    use HasFactory;
    use UpdatedAtUtcTrait;

    protected $table = 'products';

    protected $attributes = [
        'version' => 1,
    ];

    protected $fillable = [
        'name',
        'description',
        'price',
        'quantity',
        'category_id',
    ];

    protected static function booted(): void
    {
        // Optimistic locking: every real change (including stock movements)
        // bumps the version so stale edits can be detected.
        static::updating(static function (self $product): void {
            if ($product->isDirty() && ! $product->isDirty('version')) {
                $product->version++;
            }
        });
    }

    /**
     * @return BelongsTo<CategoryModel, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(CategoryModel::class, 'category_id');
    }
}
