<?php

namespace App\Modules\Catalog\Database\Models;

use App\Foundation\Money\Money;
use App\Foundation\Money\MoneyCast;
use App\Modules\Catalog\Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $category_id
 * @property string $name
 * @property string $slug
 * @property string|null $sku
 * @property Money $price
 * @property Money|null $cost_price
 * @property int $stock_quantity
 * @property bool $is_active
 */
#[UseFactory(ProductFactory::class)]
#[Fillable(['category_id', 'name', 'slug', 'sku', 'description', 'price', 'cost_price', 'stock_quantity', 'attributes', 'image', 'is_active'])]
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    protected $attributes = [
        'stock_quantity' => 0,
        'is_active' => true,
    ];

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeLowStock(Builder $query): Builder
    {
        return $query->where('stock_quantity', '<=', config('catalog.low_stock_threshold'));
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => MoneyCast::class,
            'cost_price' => MoneyCast::class,
            'stock_quantity' => 'integer',
            'attributes' => 'array',
            'is_active' => 'boolean',
        ];
    }
}
