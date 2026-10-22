<?php

namespace App\Modules\Catalog\Database\Models;

use App\Foundation\Money\Money;
use App\Foundation\Money\MoneyCast;
use App\Foundation\Text\SearchText;
use App\Modules\Catalog\Contracts\Data\ProductData;
use App\Modules\Catalog\Contracts\Enums\SaleUnit;
use App\Modules\Catalog\Database\Factories\ProductFactory;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One thing on the shelf, at one price.
 *
 * Each pack size is its own product — «أرز الضحى 1 كجم» and «أرز الضحى 5 كجم»
 * are two — the way each has its own barcode. `price` is per `unit`: per
 * piece, per kilo, per litre. `search_name` is written on every save from the
 * name and `search_terms`, folded by {@see SearchText}; nothing else writes it.
 *
 * Private to this module: other modules see a {@see ProductData}, through
 * `ProductRepositoryInterface`.
 *
 * @property int $id
 * @property int $category_id
 * @property string $name
 * @property SaleUnit $unit
 * @property string|null $search_terms
 * @property string $search_name
 * @property string $slug
 * @property string|null $sku
 * @property string|null $barcode
 * @property Money $price
 * @property Money|null $cost_price
 * @property int $stock_quantity
 * @property bool $is_active
 * @property CarbonImmutable|null $deleted_at
 */
#[UseFactory(ProductFactory::class)]
#[Fillable(['category_id', 'name', 'unit', 'search_terms', 'slug', 'sku', 'barcode', 'description', 'price', 'cost_price', 'stock_quantity', 'attributes', 'image', 'is_active'])]
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    // Deleted, it goes to the trash: out of every list, kept until it is
    // brought back or let go of for good.
    use SoftDeletes;

    protected $attributes = [
        'unit' => 'piece',
        'search_name' => '',
        'stock_quantity' => 0,
        'is_active' => true,
    ];

    protected static function booted(): void
    {
        static::saving(static function (Product $product): void {
            $product->search_name = SearchText::normalize(trim($product->name.' '.$product->search_terms));
        });
    }

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
        // Qualified: joined to its categories, both tables have the column.
        return $query->where($query->qualifyColumn('is_active'), true);
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
     * This product, as other modules may see it.
     */
    public function toData(): ProductData
    {
        return new ProductData(
            id: $this->getKey(),
            categoryId: $this->category_id,
            name: $this->name,
            unit: $this->unit,
            price: $this->price,
            searchName: $this->search_name,
            searchTerms: $this->search_terms,
            barcode: $this->barcode,
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
            'unit' => SaleUnit::class,
            'price' => MoneyCast::class,
            'cost_price' => MoneyCast::class,
            'stock_quantity' => 'integer',
            'attributes' => 'array',
            'is_active' => 'boolean',
        ];
    }
}
