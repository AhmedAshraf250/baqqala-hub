<?php

namespace App\Modules\Catalog\Domain\Queries;

use App\Foundation\Text\Numerals;
use App\Foundation\Text\SearchText;
use App\Modules\Catalog\Database\Models\Category;
use App\Modules\Catalog\Database\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * The products screen's list: found by what the counter calls a product or
 * by one of its codes, narrowed to a shelf and the shelves under it, on sale
 * or archived — in shelf order, the way the picker shows them.
 */
final class ProductList
{
    public const int PER_PAGE = 50;

    /**
     * @return LengthAwarePaginator<int, Product>
     */
    public function page(string $term, ?int $categoryId, bool $archived): LengthAwarePaginator
    {
        return $this->matching($term, $categoryId)
            ->where('products.is_active', ! $archived)
            ->join('categories as shelf', 'shelf.id', '=', 'products.category_id')
            ->leftJoin('categories as above', 'above.id', '=', 'shelf.parent_id')
            // A sub-shelf sits with the shelf above it, after it.
            ->orderByRaw('COALESCE(above.sort_order, shelf.sort_order)')
            ->orderByRaw('COALESCE(above.name, shelf.name)')
            ->orderByRaw('shelf.parent_id IS NOT NULL')
            ->orderBy('shelf.sort_order')
            ->orderBy('shelf.name')
            ->orderBy('products.name')
            ->select('products.*')
            ->with('category.parent')
            ->paginate(self::PER_PAGE);
    }

    /**
     * How many products match on sale and archived, so each choice says what
     * it holds before it is made.
     *
     * @return array{active: int, archived: int}
     */
    public function counts(string $term, ?int $categoryId): array
    {
        $counts = $this->matching($term, $categoryId)
            ->toBase()
            ->selectRaw('COUNT(*) AS total, COALESCE(SUM(CASE WHEN products.is_active THEN 1 ELSE 0 END), 0) AS active')
            ->first();

        $total = (int) ($counts->total ?? 0);
        $active = (int) ($counts->active ?? 0);

        return ['active' => $active, 'archived' => $total - $active];
    }

    /**
     * @return Builder<Product>
     */
    private function matching(string $term, ?int $categoryId): Builder
    {
        $query = Product::query();
        $folded = SearchText::normalize($term);
        $code = trim(Numerals::toLatin($term));

        if ($folded !== '') {
            $query->where(static function (Builder $query) use ($folded, $code): void {
                $query->where(static function (Builder $query) use ($folded): void {
                    foreach (explode(' ', $folded) as $word) {
                        $query->where('products.search_name', 'like', "%{$word}%");
                    }
                })
                    ->orWhere('products.barcode', $code)
                    ->orWhere('products.sku', $code);
            });
        }

        if ($categoryId !== null) {
            $query->where(static function (Builder $query) use ($categoryId): void {
                $query->where('products.category_id', $categoryId)
                    ->orWhereIn('products.category_id', Category::query()->select('id')->where('parent_id', $categoryId));
            });
        }

        return $query;
    }
}
