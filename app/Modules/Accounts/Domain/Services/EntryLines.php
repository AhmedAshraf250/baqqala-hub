<?php

namespace App\Modules\Accounts\Domain\Services;

use App\Modules\Accounts\Contracts\Data\ChargeLine;
use App\Modules\Accounts\Database\Models\AccountTransaction;
use App\Modules\Accounts\Database\Models\AccountTransactionItem;
use App\Modules\Catalog\Contracts\Data\ProductData;
use App\Modules\Catalog\Contracts\ProductRepositoryInterface;
use Illuminate\Support\Collection;

/**
 * A charge's lines, as the ledger takes them again — for an entry moved to
 * the right customer, or opened to be corrected.
 *
 * Each comes back as it was sold: a product at the price it was sold at, and
 * a loose one sold by amount as that amount — so the figure does not drift by
 * a rounding. A product no longer for sale comes back as a line by its name
 * and what it came to, since the catalog would refuse it.
 */
final readonly class EntryLines
{
    public function __construct(private ProductRepositoryInterface $products) {}

    /**
     * @return list<ChargeLine>
     */
    public function chargeLines(AccountTransaction $entry): array
    {
        $products = $this->productsOf($entry);

        return array_values($entry->items->map(static function (AccountTransactionItem $item) use ($products): ChargeLine {
            $product = $item->product_id === null ? null : $products->get($item->product_id);

            if ($product === null || ! $product->isActive) {
                return ChargeLine::free($item->name, $item->line_total);
            }

            return self::soldByAmount($item)
                ? ChargeLine::productByAmount($product->id, $item->line_total)
                : ChargeLine::product($product->id, $item->quantity, $item->unit_price);
        })->all());
    }

    /**
     * The products the entry's lines name, as the catalog has them now,
     * keyed by id.
     *
     * @return Collection<int, ProductData>
     */
    public function productsOf(AccountTransaction $entry): Collection
    {
        $ids = array_values(array_unique(array_filter($entry->items->pluck('product_id')->all())));

        return $this->products->findMany(array_map(intval(...), $ids));
    }

    /**
     * Whether a line was sold by what it cost: its total is not what its
     * quantity comes to at its price.
     */
    public static function soldByAmount(AccountTransactionItem $item): bool
    {
        return $item->product_id !== null && ! ChargeLinePricer::lineTotal($item->quantity, $item->unit_price)->equals($item->line_total);
    }
}
