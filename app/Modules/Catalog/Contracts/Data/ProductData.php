<?php

namespace App\Modules\Catalog\Contracts\Data;

use App\Foundation\Money\Money;
use App\Modules\Catalog\Contracts\Enums\SaleUnit;

/**
 * A product, as other modules see it.
 *
 * Immutable and inert: a module selling it reads its name, unit, and price,
 * and copies what it needs onto its own record — a sale keeps the price it
 * was sold at, whatever the catalog says later.
 *
 * `price` is per unit: per piece, per kilo, per litre. `searchName` is the
 * name and its other names folded for a search, ready to compare.
 */
final readonly class ProductData
{
    public function __construct(
        public int $id,
        public int $categoryId,
        public string $name,
        public SaleUnit $unit,
        public Money $price,
        public string $searchName,
        public ?string $searchTerms = null,
        public ?string $barcode = null,
        public bool $isActive = true,
    ) {}
}
