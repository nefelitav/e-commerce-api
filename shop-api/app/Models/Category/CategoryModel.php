<?php

namespace App\Models\Category;

use App\Models\CreatedAtUtcTrait;
use App\Models\UpdatedAtUtcTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property int|null $parent_id
 * @property CategoryModel $parent
 * @method static static create(array $attributes = [])
 */
class CategoryModel extends Model
{
    use CreatedAtUtcTrait;
    use UpdatedAtUtcTrait;

    protected $table = 'categories';

    protected $fillable = [
        'name',
        'description',
        'parent_id',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
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

    public function getParentId(): ?int
    {
        return $this->parent_id;
    }

    public function getParent(): ?CategoryModel
    {
        return $this->parent;
    }
}
