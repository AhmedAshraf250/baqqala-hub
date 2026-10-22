<?php

namespace App\Modules\Catalog\Domain\Actions;

use App\Foundation\Money\Money;
use App\Modules\Catalog\Contracts\Enums\SaleUnit;
use App\Modules\Catalog\Database\Models\Category;
use App\Modules\Catalog\Database\Models\Product;
use Illuminate\Support\Facades\DB;

/**
 * Adds the starter catalog's categories and products that are not there yet.
 *
 * Every record in the file has a key that is its slug, and a key already in
 * the database is left exactly as it is: a price the owner corrected, a name
 * they changed, a product they archived. So it is safe to run again — after
 * adding lines to the file, it adds those lines only. A product deleted since
 * comes back; one archived stays archived.
 *
 * A new product joins its category whatever has become of the category since,
 * renamed or reordered, because the key still finds it.
 */
final class InstallStarterCatalog
{
    /**
     * @return array{categories: int, products: int, unchanged: int}
     */
    public function handle(?string $file = null): array
    {
        /** @var array<string, array{name: string, icon?: string, products: list<array{key: string, name: string, unit: string, price: string, terms?: string}>}> $catalog */
        $catalog = require $file ?? $this->file();

        return DB::transaction(function () use ($catalog): array {
            // What the owner deleted counts as there: it is not brought back.
            $categories = Category::withTrashed()->pluck('id', 'slug');
            $products = Product::withTrashed()->pluck('id', 'slug');
            $added = ['categories' => 0, 'products' => 0, 'unchanged' => 0];

            foreach (array_keys($catalog) as $position => $key) {
                $shelf = $catalog[$key];

                $categoryId = $categories[$key] ?? null;

                if ($categoryId === null) {
                    $categoryId = Category::query()->create([
                        'name' => $shelf['name'],
                        'slug' => $key,
                        'icon' => $shelf['icon'] ?? null,
                        // Spaced out, so the owner can slot a shelf between two.
                        'sort_order' => ($position + 1) * 10,
                    ])->getKey();

                    $added['categories']++;
                }

                foreach ($shelf['products'] as $product) {
                    if (isset($products[$product['key']])) {
                        $added['unchanged']++;

                        continue;
                    }

                    Product::query()->create([
                        'category_id' => $categoryId,
                        'name' => $product['name'],
                        'slug' => $product['key'],
                        'unit' => SaleUnit::from($product['unit']),
                        'price' => Money::fromDecimalString($product['price']),
                        'search_terms' => $product['terms'] ?? null,
                    ]);

                    $added['products']++;
                }
            }

            return $added;
        });
    }

    /**
     * The catalog that ships with the module.
     */
    public function file(): string
    {
        return dirname(__DIR__, 2).'/Database/Seeders/starter-catalog.php';
    }
}
