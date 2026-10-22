<?php

namespace App\Modules\Accounts\Contracts\Data;

use App\Foundation\Money\Money;

/**
 * One line of a charge, as the counter enters it.
 *
 * Three shapes, each with its own constructor:
 *
 * - {@see free()}: a name and an amount, with no product — «حاجات متفرقة 20».
 * - {@see product()}: a product and a quantity, at the catalog's price or at
 *   the price the counter set.
 * - {@see productByAmount()}: a loose product sold by what it costs —
 *   «لب بـ10 جنيه» — whose weight follows from its price.
 *
 * The ledger prices every line again from the catalog when it saves: what a
 * line carries is what the person chose, never what anything costs.
 * Quantities are thousandths of the product's unit — two pieces are 2000,
 * a quarter kilo 250.
 */
final readonly class ChargeLine
{
    private function __construct(
        public ?int $productId,
        public ?string $name,
        public int $quantity,
        public ?Money $unitPrice,
        public ?Money $amount,
        public ?Money $expectedListPrice,
    ) {}

    /**
     * A line with no product: a name and what it came to.
     */
    public static function free(string $name, Money $amount): self
    {
        return new self(null, $name, 1000, null, $amount, null);
    }

    /**
     * A product and how much of it, in thousandths of its unit.
     *
     * @param  Money|null  $unitPrice  The price the counter set; null for the catalog's.
     * @param  Money|null  $expectedListPrice  The catalog price the person saw; when given
     *                                         and the catalog has changed since, the save is
     *                                         refused rather than charging a price nobody saw.
     */
    public static function product(int $productId, int $quantity, ?Money $unitPrice = null, ?Money $expectedListPrice = null): self
    {
        return new self($productId, null, $quantity, $unitPrice, null, $expectedListPrice);
    }

    /**
     * A loose product sold by what it costs; its quantity follows from its
     * price per unit.
     */
    public static function productByAmount(int $productId, Money $amount, ?Money $expectedListPrice = null): self
    {
        return new self($productId, null, 0, null, $amount, $expectedListPrice);
    }

    public function isFree(): bool
    {
        return $this->productId === null;
    }

    public function isSoldByAmount(): bool
    {
        return $this->productId !== null && $this->amount !== null;
    }
}
