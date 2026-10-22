<?php

namespace App\Modules\Catalog\Domain\Actions;

use App\Foundation\Audit\AuditAction;
use App\Foundation\Audit\AuditTrail;
use App\Foundation\Money\Money;
use App\Modules\Catalog\Contracts\Enums\SaleUnit;
use App\Modules\Catalog\Database\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Adds a product to the catalog, or changes one: its name, shelf, unit,
 * price, and the other names and codes it is found by.
 *
 * A new product's slug is made from its name once and kept, whatever the name
 * becomes; the starter catalog's slugs are its keys. What it was and what it
 * became go into the change log.
 */
final class SaveProduct
{
    /** What the change log calls each field. */
    private const array LABELS = [
        'name' => 'catalog::module.product.name',
        'category' => 'catalog::module.product.category',
        'unit' => 'catalog::module.product.unit',
        'price' => 'catalog::module.product.price',
        'search_terms' => 'catalog::module.product.search_terms',
        'barcode' => 'catalog::module.product.barcode',
        'sku' => 'catalog::module.product.sku',
    ];

    public function __construct(private readonly AuditTrail $audit) {}

    /**
     * @param  array{category_id: int, name: string, unit: SaleUnit, price: Money, search_terms: string|null, barcode: string|null, sku: string|null}  $details
     */
    public function handle(array $details, ?int $productId = null): Product
    {
        return DB::transaction(function () use ($details, $productId): Product {
            $product = $productId === null ? new Product : Product::query()->findOrFail($productId);
            $isNew = ! $product->exists;
            $before = $isNew ? [] : self::fields($product);

            $product->fill($details);

            if ($isNew) {
                $product->slug = $this->slugFor($details['name']);
            }

            $product->save();

            $this->audit->record(
                'catalog.product',
                $product->id,
                $product->name,
                'catalog::module.audit.product',
                $isNew ? AuditAction::Created : AuditAction::Updated,
                AuditTrail::differences($before, self::fields($product), self::LABELS, ['unit']),
            );

            return $product;
        });
    }

    /**
     * A product's fields as the change log shows them.
     *
     * @return array<string, string|null>
     */
    public static function fields(Product $product): array
    {
        return [
            'name' => $product->name,
            'category' => $product->category()->value('name'),
            'unit' => 'catalog::module.unit.'.$product->unit->value,
            'price' => $product->price->toDisplayString(),
            'search_terms' => $product->search_terms,
            'barcode' => $product->barcode,
            'sku' => $product->sku,
        ];
    }

    private function slugFor(string $name): string
    {
        $base = Str::slug($name) ?: 'product';
        $slug = $base;

        // A slug in the trash is still taken: it comes back with its own.
        while (Product::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.Str::lower(Str::random(4));
        }

        return $slug;
    }
}
