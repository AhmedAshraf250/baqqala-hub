<?php

namespace App\Modules\Catalog\Database\Repositories;

use App\Modules\Catalog\Contracts\Data\ProductData;
use App\Modules\Catalog\Contracts\ProductRepositoryInterface;
use App\Modules\Catalog\Database\Models\Category;
use App\Modules\Catalog\Database\Models\Product;
use Illuminate\Container\Attributes\Scoped;
use Illuminate\Support\Collection;

/**
 * Products, read from this module's own tables.
 *
 * One instance per request, like everything this module hands out.
 */
#[Scoped]
final class ProductRepository implements ProductRepositoryInterface
{
    public function find(int $productId): ?ProductData
    {
        return Product::query()->find($productId)?->toData();
    }

    /**
     * @param  list<int>  $productIds
     * @return Collection<int, ProductData>
     */
    public function findMany(array $productIds): Collection
    {
        if ($productIds === []) {
            return collect();
        }

        return Product::query()
            ->whereKey($productIds)
            ->get()
            ->mapWithKeys(static fn (Product $product): array => [$product->getKey() => $product->toData()]);
    }

    /**
     * @return Collection<int, ProductData>
     */
    public function getActive(): Collection
    {
        return Product::query()
            ->active()
            ->join('categories', 'categories.id', '=', 'products.category_id')
            ->orderBy('categories.sort_order')
            ->orderBy('categories.name')
            ->orderBy('products.name')
            ->select('products.*')
            ->get()
            ->mapWithKeys(static fn (Product $product): array => [$product->getKey() => $product->toData()]);
    }

    public function getCatalogVersion(): string
    {
        // Counts catch a deletion; the latest change catches an edit — but
        // only to the second, so sums over what a picker shows catch two
        // edits within one: a price, a rename, an archiving. Both tables,
        // because a renamed or reordered category changes what a picker shows
        // as much as a new price does.
        $state = [
            (array) Product::query()->toBase()
                ->selectRaw('COUNT(*) AS total, MAX(updated_at) AS changed, SUM(price) AS prices, SUM(is_active) AS active, SUM(LENGTH(name)) AS names, SUM(category_id) AS shelves')
                ->first(),
            (array) Category::query()->toBase()
                ->selectRaw('COUNT(*) AS total, MAX(updated_at) AS changed, SUM(sort_order) AS places, SUM(is_active) AS active, SUM(LENGTH(name)) AS names')
                ->first(),
        ];

        return substr(sha1((string) json_encode($state)), 0, 16);
    }
}
