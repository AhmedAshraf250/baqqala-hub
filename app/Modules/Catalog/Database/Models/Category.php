<?php

namespace App\Modules\Catalog\Database\Models;

use App\Modules\Catalog\Contracts\Data\CategoryData;
use App\Modules\Catalog\Database\Factories\CategoryFactory;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A shelf of the catalog. A product sits on exactly one; a category may sit
 * under another, two levels at most.
 *
 * @property int $id
 * @property int|null $parent_id
 * @property string $name
 * @property string|null $icon
 * @property int $sort_order
 * @property string $slug
 * @property bool $is_active
 * @property CarbonImmutable|null $deleted_at
 */
#[UseFactory(CategoryFactory::class)]
#[Fillable(['parent_id', 'name', 'icon', 'sort_order', 'slug', 'image', 'is_active'])]
class Category extends Model
{
    /** @use HasFactory<CategoryFactory> */
    use HasFactory;

    // Deleted, it goes to the trash: out of every list, kept until it is
    // brought back or let go of for good.
    use SoftDeletes;

    /**
     * The icons a category's tile may carry, from Bootstrap Icons: the
     * starter catalog's, and a few more a grocery's shelves call for.
     *
     * @var list<string>
     */
    public const array ICONS = [
        'basket', 'basket2', 'bag', 'bag-heart', 'box-seam', 'bucket', 'cookie', 'cup',
        'cup-hot', 'cup-straw', 'droplet', 'egg', 'egg-fried', 'fire', 'flower1', 'flower2',
        'heart', 'snow', 'stars', 'tree', 'apple', 'gift', 'capsule', 'brush',
        'house', 'lightbulb', 'battery-full', 'scissors', 'tag', 'three-dots',
    ];

    protected $attributes = [
        'sort_order' => 0,
        'is_active' => true,
    ];

    /**
     * @return BelongsTo<self, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return HasMany<self, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /**
     * @return HasMany<Product, $this>
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /**
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where($query->qualifyColumn('is_active'), true);
    }

    /**
     * In the order the owner put them, then by name.
     *
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeInOrder(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    /**
     * This category, as other modules may see it.
     */
    public function toData(): CategoryData
    {
        return new CategoryData(
            id: $this->getKey(),
            name: $this->name,
            parentId: $this->parent_id,
            icon: $this->icon,
            sortOrder: $this->sort_order,
            isActive: $this->is_active,
        );
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
