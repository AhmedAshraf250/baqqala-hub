<?php

namespace App\Modules\Accounts\Domain\Services;

use App\Foundation\Money\Money;
use App\Modules\Accounts\Contracts\Data\ChargeLine;
use App\Modules\Accounts\Contracts\Exceptions\PriceChangedMeanwhile;
use App\Modules\Accounts\Contracts\Exceptions\ProductUnavailable;
use App\Modules\Catalog\Contracts\Data\ProductData;
use App\Modules\Catalog\Contracts\Enums\SaleUnit;
use App\Modules\Catalog\Contracts\ProductRepositoryInterface;
use InvalidArgumentException;

/**
 * Turns the lines a person entered into the lines the ledger stores.
 *
 * Every product is read again from the catalog: a line carries what was
 * chosen, never what anything costs. The name, unit, and catalog price are
 * copied onto the line, and each line's total is worked out here, rounded to
 * the minor unit on the line itself — so what the screen added and what is
 * stored can never differ by a piastre.
 *
 * All of it is integer arithmetic: prices in minor units, quantities in
 * thousandths of the unit.
 */
final readonly class ChargeLinePricer
{
    /** Ten thousand of anything on one line, at most. */
    private const int MAX_QUANTITY = 10_000 * SaleUnit::SCALE;

    /** A billion of the currency per unit, at most — far past any shelf. */
    private const int MAX_UNIT_PRICE = 100_000_000_000;

    public function __construct(private ProductRepositoryInterface $products) {}

    /**
     * A quantity, in thousandths of the unit, at a price per unit: rounded
     * half up to the minor unit, on the line itself. The screen that shows a
     * line's total asks here too, so what it shows is what is stored.
     */
    public static function lineTotal(int $quantity, Money $unitPrice): Money
    {
        return Money::fromMinorUnits(intdiv($unitPrice->minorUnits * $quantity + intdiv(SaleUnit::SCALE, 2), SaleUnit::SCALE));
    }

    /**
     * What a sum buys at a price per unit, in thousandths of the unit, to the
     * nearest thousandth. Zero for a price that is not above zero.
     */
    public static function quantityFor(Money $amount, Money $unitPrice): int
    {
        if (! $unitPrice->isPositive()) {
            return 0;
        }

        return intdiv($amount->minorUnits * SaleUnit::SCALE + intdiv($unitPrice->minorUnits, 2), $unitPrice->minorUnits);
    }

    /**
     * @param  list<ChargeLine>  $lines
     * @return array{items: list<array{product_id: int|null, name: string, unit: string|null, quantity: int, unit_price: Money, list_price: Money|null, line_total: Money}>, total: Money}
     */
    public function price(array $lines): array
    {
        if ($lines === []) {
            throw new InvalidArgumentException('A charge has at least one line.');
        }

        $products = $this->products->findMany(array_values(array_unique(array_filter(
            array_map(static fn (ChargeLine $line): ?int => $line->productId, $lines),
            static fn (?int $id): bool => $id !== null,
        ))));

        $items = [];
        $total = Money::zero();

        foreach ($lines as $line) {
            $item = $line->isFree() ? $this->free($line) : $this->product($line, $products->get($line->productId));

            $items[] = $item;
            $total = $total->plus($item['line_total']);
        }

        if (! $total->isPositive()) {
            throw new InvalidArgumentException('A charge has to come to more than zero.');
        }

        return ['items' => $items, 'total' => $total];
    }

    /**
     * @return array{product_id: null, name: string, unit: null, quantity: int, unit_price: Money, list_price: null, line_total: Money}
     */
    private function free(ChargeLine $line): array
    {
        $name = trim((string) $line->name);
        $amount = $line->amount ?? Money::zero();

        if ($name === '') {
            throw new InvalidArgumentException('A line without a product needs a name.');
        }

        if (! $amount->isPositive()) {
            throw new InvalidArgumentException('A line without a product needs an amount above zero.');
        }

        return [
            'product_id' => null,
            'name' => $name,
            'unit' => null,
            'quantity' => SaleUnit::SCALE,
            'unit_price' => $amount,
            'list_price' => null,
            'line_total' => $amount,
        ];
    }

    /**
     * @return array{product_id: int, name: string, unit: string, quantity: int, unit_price: Money, list_price: Money, line_total: Money}
     */
    private function product(ChargeLine $line, ?ProductData $product): array
    {
        if ($product === null || ! $product->isActive) {
            throw new ProductUnavailable((int) $line->productId);
        }

        if ($line->expectedListPrice !== null && ! $line->expectedListPrice->equals($product->price)) {
            throw new PriceChangedMeanwhile($product->id, $line->expectedListPrice, $product->price);
        }

        $unitPrice = $line->isSoldByAmount() ? $product->price : ($line->unitPrice ?? $product->price);

        if ($unitPrice->isNegative() || $unitPrice->minorUnits > self::MAX_UNIT_PRICE) {
            throw new InvalidArgumentException('A unit price is zero or more, and within reason.');
        }

        [$quantity, $lineTotal] = $line->isSoldByAmount()
            ? $this->byAmount($product, $unitPrice, $line->amount ?? Money::zero())
            : $this->byQuantity($product, $unitPrice, $line->quantity);

        return [
            'product_id' => $product->id,
            'name' => $product->name,
            'unit' => $product->unit->value,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'list_price' => $product->price,
            'line_total' => $lineTotal,
        ];
    }

    /**
     * A quantity at a price: the total rounded half up to the minor unit.
     *
     * @return array{0: int, 1: Money}
     */
    private function byQuantity(ProductData $product, Money $unitPrice, int $quantity): array
    {
        if ($quantity <= 0 || $quantity > self::MAX_QUANTITY) {
            throw new InvalidArgumentException('A quantity is above zero and at most ten thousand.');
        }

        if (! $product->unit->allowsFractions() && $quantity % SaleUnit::SCALE !== 0) {
            throw new InvalidArgumentException("[{$product->name}] is sold by the piece, so its quantity is a whole number.");
        }

        return [$quantity, self::lineTotal($quantity, $unitPrice)];
    }

    /**
     * What a sum buys of a loose product: the total is what was asked for,
     * and the quantity follows from the price, to the nearest thousandth.
     *
     * @return array{0: int, 1: Money}
     */
    private function byAmount(ProductData $product, Money $unitPrice, Money $amount): array
    {
        if (! $product->unit->allowsFractions()) {
            throw new InvalidArgumentException("[{$product->name}] is sold by the piece, not by amount.");
        }

        if (! $amount->isPositive() || ! $unitPrice->isPositive()) {
            throw new InvalidArgumentException('Selling by amount needs an amount and a price above zero.');
        }

        $quantity = self::quantityFor($amount, $unitPrice);

        if ($quantity <= 0 || $quantity > self::MAX_QUANTITY) {
            throw new InvalidArgumentException('That amount buys too little, or too much, to weigh.');
        }

        return [$quantity, $amount];
    }
}
