<?php

namespace App\Models\Category;

use App\Models\CreatedAtUtcTrait;
use App\Models\Product\ProductModel;
use App\Models\UpdatedAtUtcTrait;
use Database\Factories\Category\CategoryModelFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Collection;

/**
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property int|null $parent_id
 * @property CategoryModel $parent
 * @method static static create(array<mixed> $attributes = [])
 * @method static static|null find(int|string $id, array<int, string> $columns = ['*'])
 * @property Collection<int, ProductModel> $products
 * @property Collection<int, CategoryModel> $children
 */
class CategoryModel extends Model
{
    use CreatedAtUtcTrait;
    use UpdatedAtUtcTrait;
    /** @use HasFactory<CategoryModelFactory> */
    use HasFactory;

    protected $table = 'categories';

    protected $fillable = [
        'name',
        'description',
        'parent_id',
    ];

    /**
     * @return BelongsTo<CategoryModel, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return HasMany<CategoryModel, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /**
     * @return HasMany<ProductModel, $this>
     */
    public function products(): HasMany
    {
        return $this->hasMany(ProductModel::class, 'category_id');
    }
}
