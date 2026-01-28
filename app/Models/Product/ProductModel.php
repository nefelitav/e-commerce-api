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
 * @property int $category_id
 * @property CategoryModel $category
 * @method static static create(array<mixed> $attributes = [])
 * @method static Builder<ProductModel> query()
 * @method static static|null find(int|string $id, array<int, string> $columns = ['*'])
 */
class ProductModel extends Model
{
    use CreatedAtUtcTrait;
    use UpdatedAtUtcTrait;
    /** @use HasFactory<ProductModelFactory> */
    use HasFactory;

    protected $table = 'products';

    protected $fillable = [
        'name',
        'description',
        'price',
        'quantity',
        'category_id',
    ];

    /**
     * @return BelongsTo<CategoryModel, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(CategoryModel::class, 'category_id');
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getCategoryId(): int
    {
        return $this->category_id;
    }

    public function getPrice(): float
    {
        return $this->price;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function getCategory(): ?CategoryModel
    {
        return $this->category;
    }
}
