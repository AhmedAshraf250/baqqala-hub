<?php

namespace App\Modules\Catalog\Areas\Admin\Search;

use App\Admin\Contracts\Authorization\PermissionInterface;
use App\Admin\Contracts\Search\SearchSourceInterface;
use App\Admin\Search\SearchResult;
use App\Foundation\Text\Numerals;
use App\Foundation\Text\SearchText;
use App\Modules\Catalog\Authorization\CatalogPermission;
use App\Modules\Catalog\Database\Models\Product;

/**
 * Products on sale in the header's quick search, found the way the picker
 * finds them — a name, another name, a barcode — each with its shelf and its
 * price, opening the products screen at it.
 */
final class ProductSearch implements SearchSourceInterface
{
    public function label(): string
    {
        return 'catalog::module.search.products';
    }

    public function permission(): PermissionInterface
    {
        return CatalogPermission::View;
    }

    /**
     * @return list<SearchResult>
     */
    public function search(string $term, int $limit): array
    {
        $words = array_filter(explode(' ', SearchText::normalize($term)));
        $code = trim(Numerals::toLatin($term));

        if ($words === []) {
            return [];
        }

        return array_values(Product::query()
            ->active()
            ->with('category')
            ->where(static function ($query) use ($words, $code): void {
                $query->where(static function ($query) use ($words): void {
                    foreach ($words as $word) {
                        $query->where('search_name', 'like', "%{$word}%");
                    }
                })->orWhere('barcode', $code)->orWhere('sku', $code);
            })
            ->orderBy('name')
            ->limit($limit)
            ->get()
            ->map(static fn (Product $product): SearchResult => new SearchResult(
                title: $product->name,
                url: route('admin.catalog.products.index', ['q' => $product->name]),
                subtitle: $product->category?->name,
                badge: __('catalog::module.search.price', ['price' => $product->price->toDisplayString(), 'unit' => $product->unit->label()]),
                icon: 'box-seam',
            ))
            ->all());
    }

    /**
     * @return list<SearchResult>
     */
    public function suggestions(int $limit): array
    {
        return [];
    }

    public function fallback(string $term): ?SearchResult
    {
        return null;
    }
}
