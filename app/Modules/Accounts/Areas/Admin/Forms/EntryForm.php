<?php

namespace App\Modules\Accounts\Areas\Admin\Forms;

use App\Foundation\Money\Money;
use App\Foundation\Text\Numerals;
use App\Modules\Accounts\Contracts\Data\ChargeLine;
use App\Modules\Accounts\Contracts\Enums\OverpaymentChoice;
use App\Modules\Accounts\Database\Models\AccountTransactionItem;
use App\Modules\Accounts\Domain\Services\ChargeLinePricer;
use App\Modules\Accounts\Domain\Services\EntryLines;
use App\Modules\Catalog\Contracts\Data\CategoryData;
use App\Modules\Catalog\Contracts\Data\ProductData;
use App\Modules\Catalog\Contracts\Enums\SaleUnit;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Form;

/**
 * What the entry drawer holds while someone types: «أخذ» as a list of lines,
 * or «دفع» as an amount.
 *
 * Everything is kept as it was typed — «٣٥٠», «0.25», «.5» — and read here,
 * so a half-typed figure is never an error until the save. A line's total is
 * worked out with the same arithmetic the ledger stores it with; the ledger
 * then prices every product line again from the catalog, so nothing this
 * form says about a price is ever what gets charged unless the catalog
 * agrees.
 *
 * A line is `key` (stable across redraws), and either a product — `product`,
 * `name`, `unit`, `category`, `quantity` or (`byAmount`) `amount`, `price`
 * the person may change, `listPrice` as the catalog had it — or a free line:
 * a `name` and an `amount`.
 */
final class EntryForm extends Form
{
    /** The most one entry may come to: a sum no shop takes on account by mistake. */
    public const string MAX_AMOUNT = '1000000.00';

    public string $kind = 'charge';

    /**
     * @var list<array{key: string, product: int|null, name: string, unit: string|null, category: string|null, byAmount: bool, quantity: string, price: string, listPrice: string|null, amount: string}>
     */
    public array $lines = [];

    public string $note = '';

    public bool $payingNow = false;

    public string $paidNow = '';

    public string $amount = '';

    public string $overpayment = 'owed_only';

    /**
     * An amount as typed, or null when it is not one: digits in either
     * script, at most two after the point, zero or more.
     */
    public static function money(string $typed): ?Money
    {
        $typed = trim(Numerals::toLatin($typed));

        if (str_starts_with($typed, '.')) {
            $typed = '0'.$typed;
        }

        if (preg_match('/^\d{1,12}(\.\d{1,2})?$/', $typed) !== 1) {
            return null;
        }

        return Money::fromDecimalString($typed);
    }

    /**
     * A quantity as typed, in thousandths of the unit, or null when it is
     * not one: above zero, whole for a product sold by the piece.
     */
    public static function quantity(string $typed, ?SaleUnit $unit): ?int
    {
        $typed = trim(Numerals::toLatin($typed));

        if (str_starts_with($typed, '.')) {
            $typed = '0'.$typed;
        }

        if (preg_match('/^(\d{1,5})(?:\.(\d{1,3}))?$/', $typed, $parts) !== 1) {
            return null;
        }

        $thousandths = (int) $parts[1] * SaleUnit::SCALE + (int) str_pad($parts[2] ?? '', 3, '0');

        if ($thousandths <= 0 || ($unit !== null && ! $unit->allowsFractions() && $thousandths % SaleUnit::SCALE !== 0)) {
            return null;
        }

        return $thousandths;
    }

    /**
     * A quantity in thousandths, as a person reads and edits it: «2», «0.25».
     */
    public static function quantityText(int $thousandths): string
    {
        $whole = intdiv($thousandths, SaleUnit::SCALE);
        $fraction = rtrim(str_pad((string) ($thousandths % SaleUnit::SCALE), 3, '0', STR_PAD_LEFT), '0');

        return $fraction === '' ? (string) $whole : $whole.'.'.$fraction;
    }

    public function isCharge(): bool
    {
        return $this->kind !== 'payment';
    }

    /**
     * Add a line with no product, and return its key.
     */
    public function addFreeLine(string $name, string $amount = ''): string
    {
        $key = (string) Str::uuid();

        $this->lines[] = [
            'key' => $key,
            'product' => null,
            'name' => $name,
            'unit' => null,
            'category' => null,
            'byAmount' => false,
            'quantity' => '1',
            'price' => '',
            'listPrice' => null,
            'amount' => $amount,
        ];

        return $key;
    }

    /**
     * Add a product, or more of it when it is already on a line at the
     * catalog's price; return the line's key.
     */
    public function addProduct(ProductData $product, ?string $category, int $quantity = SaleUnit::SCALE): string
    {
        foreach ($this->lines as $index => $line) {
            if ($line['product'] === $product->id && ! $line['byAmount'] && $line['price'] === $product->price->toDecimalString()) {
                $current = self::quantity($line['quantity'], $product->unit) ?? 0;
                $this->lines[$index]['quantity'] = self::quantityText($current + $quantity);

                return $line['key'];
            }
        }

        $key = (string) Str::uuid();

        $this->lines[] = [
            'key' => $key,
            'product' => $product->id,
            'name' => $product->name,
            'unit' => $product->unit->value,
            'category' => $category,
            'byAmount' => false,
            'quantity' => self::quantityText($quantity),
            'price' => $product->price->toDecimalString(),
            'listPrice' => $product->price->toDecimalString(),
            'amount' => '',
        ];

        return $key;
    }

    /**
     * Fill the lines from a charge being corrected, each as it was sold: a
     * product at its price then, a loose one sold by amount as that amount,
     * and one no longer for sale as a line by its name and what it came to.
     *
     * @param  Collection<int, AccountTransactionItem>  $items
     * @param  Collection<int, ProductData>  $products  The catalog's products now, by id.
     * @param  Collection<int, CategoryData>  $categories  By id.
     */
    public function fillFromItems(Collection $items, Collection $products, Collection $categories): void
    {
        $this->lines = [];

        foreach ($items as $item) {
            $product = $item->product_id === null ? null : $products->get($item->product_id);

            if ($product === null || ! $product->isActive) {
                $this->addFreeLine($item->name, $item->line_total->toDecimalString());

                continue;
            }

            $byAmount = EntryLines::soldByAmount($item);

            $this->lines[] = [
                'key' => (string) Str::uuid(),
                'product' => $product->id,
                'name' => $item->name,
                'unit' => $product->unit->value,
                'category' => $categories->get($product->categoryId)?->name,
                'byAmount' => $byAmount,
                'quantity' => self::quantityText($item->quantity),
                'price' => $byAmount ? $product->price->toDecimalString() : $item->unit_price->toDecimalString(),
                'listPrice' => $product->price->toDecimalString(),
                'amount' => $byAmount ? $item->line_total->toDecimalString() : '',
            ];
        }
    }

    public function removeLine(string $key): void
    {
        $this->lines = array_values(array_filter($this->lines, static fn (array $line): bool => $line['key'] !== $key));
    }

    /**
     * One more, or one fewer, of a line sold by the piece; a quarter kilo
     * either way for one weighed. Never below the smallest step.
     */
    public function step(string $key, int $direction): void
    {
        foreach ($this->lines as $index => $line) {
            if ($line['key'] !== $key || $line['product'] === null || $line['byAmount']) {
                continue;
            }

            $unit = SaleUnit::tryFrom((string) $line['unit']);
            $step = $unit?->allowsFractions() ? SaleUnit::SCALE / 4 : SaleUnit::SCALE;
            $current = self::quantity($line['quantity'], $unit) ?? $step;

            $this->lines[$index]['quantity'] = self::quantityText(max($step, $current + $direction * $step));
        }
    }

    /**
     * Sell a loose product by what it costs rather than by its weight, or
     * back. The catalog's price stands either way.
     */
    public function sellByAmount(string $key, bool $byAmount): void
    {
        foreach ($this->lines as $index => $line) {
            if ($line['key'] === $key && $line['product'] !== null && (SaleUnit::tryFrom((string) $line['unit'])?->allowsFractions() ?? false)) {
                $this->lines[$index]['byAmount'] = $byAmount;
                $this->lines[$index]['price'] = $line['listPrice'] ?? $line['price'];
            }
        }
    }

    /**
     * Set a line's quantity to one of the quick weights.
     */
    public function setQuantity(string $key, string $quantity): void
    {
        foreach ($this->lines as $index => $line) {
            if ($line['key'] === $key && self::quantity($quantity, SaleUnit::tryFrom((string) $line['unit'])) !== null) {
                $this->lines[$index]['quantity'] = $quantity;
            }
        }
    }

    /**
     * What one line comes to, or null while it is not complete.
     *
     * @param  array{key: string, product: int|null, name: string, unit: string|null, category: string|null, byAmount: bool, quantity: string, price: string, listPrice: string|null, amount: string}  $line
     */
    public function lineTotal(array $line): ?Money
    {
        if ($line['product'] === null || $line['byAmount']) {
            $amount = self::money($line['amount']);

            return $amount?->isPositive() ? $amount : null;
        }

        $quantity = self::quantity($line['quantity'], SaleUnit::tryFrom((string) $line['unit']));
        $price = self::money($line['price']);

        return $quantity === null || $price === null ? null : ChargeLinePricer::lineTotal($quantity, $price);
    }

    /**
     * Whether the person changed a product line's price from the catalog's.
     *
     * @param  array{key: string, product: int|null, name: string, unit: string|null, category: string|null, byAmount: bool, quantity: string, price: string, listPrice: string|null, amount: string}  $line
     */
    public function priceChanged(array $line): bool
    {
        if ($line['product'] === null || $line['byAmount'] || $line['listPrice'] === null) {
            return false;
        }

        $price = self::money($line['price']);

        return $price !== null && ! $price->equals(Money::fromDecimalString($line['listPrice']));
    }

    /**
     * What the charge comes to, or null while a line is not complete or
     * there is none.
     */
    public function chargeTotal(): ?Money
    {
        if ($this->lines === []) {
            return null;
        }

        $total = Money::zero();

        foreach ($this->lines as $line) {
            $lineTotal = $this->lineTotal($line);

            if ($lineTotal === null) {
                return null;
            }

            $total = $total->plus($lineTotal);
        }

        return $total->isPositive() ? $total : null;
    }

    public function paidNowAmount(): ?Money
    {
        if (! $this->payingNow) {
            return null;
        }

        $paid = self::money($this->paidNow);

        return $paid?->isPositive() ? $paid : null;
    }

    public function paymentAmount(): ?Money
    {
        $amount = self::money($this->amount);

        return $amount?->isPositive() ? $amount : null;
    }

    public function overpaymentChoice(): OverpaymentChoice
    {
        return OverpaymentChoice::tryFrom($this->overpayment) ?? OverpaymentChoice::Refuse;
    }

    /**
     * The lines as the ledger takes them.
     *
     * A line with no product and no name is called `$unnamed`: the owner's
     * word for such lines.
     *
     * @return list<ChargeLine>
     *
     * @throws ValidationException naming the field that is not right
     */
    public function chargeLines(string $unnamed = ''): array
    {
        $errors = [];

        if ($this->lines === []) {
            $errors['form.lines'] = __('accounts::module.entry.errors.no_lines');
        }

        $lines = [];

        foreach ($this->lines as $index => $line) {
            if ($line['product'] === null) {
                $name = trim($line['name']) !== '' ? trim($line['name']) : trim($unnamed);
                $amount = $this->withinReason(self::money($line['amount']));

                if ($name === '') {
                    $errors["form.lines.{$index}.name"] = __('accounts::module.entry.errors.line_name');
                }

                if ($amount === null) {
                    $errors["form.lines.{$index}.amount"] = __('accounts::module.entry.errors.amount');
                }

                if ($name !== '' && $amount !== null) {
                    $lines[] = ChargeLine::free($name, $amount);
                }

                continue;
            }

            $listPrice = $line['listPrice'] === null ? null : Money::fromDecimalString($line['listPrice']);

            if ($line['byAmount']) {
                $amount = $this->withinReason(self::money($line['amount']));

                if ($amount === null) {
                    $errors["form.lines.{$index}.amount"] = __('accounts::module.entry.errors.amount');
                } else {
                    $lines[] = ChargeLine::productByAmount($line['product'], $amount, $listPrice);
                }

                continue;
            }

            $quantity = self::quantity($line['quantity'], SaleUnit::tryFrom((string) $line['unit']));
            $price = self::money($line['price']);

            if ($quantity === null) {
                $errors["form.lines.{$index}.quantity"] = __('accounts::module.entry.errors.quantity');
            }

            if ($price === null) {
                $errors["form.lines.{$index}.price"] = __('accounts::module.entry.errors.price');
            }

            if ($quantity !== null && $price !== null) {
                $lines[] = ChargeLine::product($line['product'], $quantity, $price, $listPrice);
            }
        }

        if ($this->payingNow && $this->paidNowAmount() === null) {
            $errors['form.paidNow'] = __('accounts::module.entry.errors.amount');
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $lines;
    }

    /**
     * @throws ValidationException
     */
    public function validPayment(): Money
    {
        return $this->withinReason($this->paymentAmount())
            ?? throw ValidationException::withMessages(['form.amount' => __('accounts::module.entry.errors.amount')]);
    }

    /**
     * The amount when it is above zero and below the most one entry may be.
     */
    private function withinReason(?Money $amount): ?Money
    {
        if ($amount === null || ! $amount->isPositive() || $amount->isGreaterThan(Money::fromDecimalString(self::MAX_AMOUNT))) {
            return null;
        }

        return $amount;
    }
}
