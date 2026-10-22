<?php

namespace App\Modules\Accounts\Database\Models;

use App\Foundation\Money\Money;
use App\Foundation\Money\MoneyCast;
use App\Modules\Accounts\Database\Factories\AccountTransactionItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * One line of a charge: a product, or a free line with a name and an amount.
 *
 * Written with its entry and never changed, like the entry. The name, unit,
 * and catalog price are copied from the product at the time, so the statement
 * reads the same after the catalog changes; `product_id` is a plain id into
 * the Catalog module, read through its repository, never a relation.
 *
 * `quantity` is thousandths of the unit. `unit_price` is what it was sold at;
 * `list_price` is what the catalog asked then — they differ when the price
 * was changed at the counter.
 *
 * @property int $id
 * @property int $transaction_id
 * @property int $position
 * @property int|null $product_id
 * @property string $name
 * @property string|null $unit
 * @property int $quantity
 * @property Money $unit_price
 * @property Money|null $list_price
 * @property Money $line_total
 */
#[UseFactory(AccountTransactionItemFactory::class)]
#[Fillable(['position', 'product_id', 'name', 'unit', 'quantity', 'unit_price', 'list_price', 'line_total'])]
class AccountTransactionItem extends Model
{
    /** @use HasFactory<AccountTransactionItemFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::updating(static function (): never {
            throw new LogicException('A line of a ledger entry is never edited.');
        });

        static::deleting(static function (): never {
            throw new LogicException('A line of a ledger entry is never deleted.');
        });
    }

    /**
     * @return BelongsTo<AccountTransaction, $this>
     */
    public function entry(): BelongsTo
    {
        return $this->belongsTo(AccountTransaction::class, 'transaction_id');
    }

    /**
     * Whether the price was changed at the counter.
     */
    public function priceWasChanged(): bool
    {
        return $this->list_price !== null && ! $this->list_price->equals($this->unit_price);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'quantity' => 'integer',
            'unit_price' => MoneyCast::class,
            'list_price' => MoneyCast::class,
            'line_total' => MoneyCast::class,
        ];
    }
}
