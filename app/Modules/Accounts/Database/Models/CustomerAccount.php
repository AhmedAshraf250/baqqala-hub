<?php

namespace App\Modules\Accounts\Database\Models;

use App\Foundation\Money\Money;
use App\Foundation\Money\MoneyCast;
use App\Modules\Accounts\Contracts\Data\AccountStanding;
use App\Modules\Accounts\Contracts\Enums\TransactionType;
use App\Modules\Accounts\Database\Factories\CustomerAccountFactory;
use App\Modules\Accounts\Domain\Actions\PostAccountTransaction;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A customer's running tab with the shop.
 *
 * `outstanding` is what the customer owes — a cached total of the account's
 * ledger, only ever written by {@see PostAccountTransaction}, and therefore
 * deliberately absent from the fillable list.
 *
 * @property int $id
 * @property int $customer_id Whose account this is. Deliberately a plain id and
 *                            not a relation: a customer is another module's
 *                            record, and reaching into it from here would tie
 *                            this module to that one's shape. Ask
 *                            `CustomerRepositoryInterface` — its `findMany()`
 *                            exists so a statement listing many accounts does
 *                            not fetch them one at a time.
 * @property Money $outstanding
 * @property Money $credit_limit
 */
#[UseFactory(CustomerAccountFactory::class)]
#[Fillable(['customer_id', 'credit_limit'])]
class CustomerAccount extends Model
{
    /** @use HasFactory<CustomerAccountFactory> */
    use HasFactory;

    protected $attributes = [
        'outstanding' => 0,
        'credit_limit' => 0,
    ];

    /**
     * @return HasMany<AccountTransaction, $this>
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(AccountTransaction::class, 'account_id');
    }

    /**
     * Recompute the outstanding total directly from the ledger.
     *
     * Used by tests and by reconciliation tooling to prove the cached total and
     * the ledger still agree.
     *
     * Summed in the database rather than in PHP: a customer who has bought on
     * account every week for years has thousands of rows, and a reconciliation
     * pass over every account would have loaded all of them into memory at
     * once. Money stays exact because the column is an integer count of minor
     * units, so this is integer arithmetic on both sides.
     */
    public function calculatedOutstanding(): Money
    {
        $total = $this->transactions()
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN type = ? THEN amount ELSE -amount END), 0) AS total',
                [TransactionType::Debit->value],
            )
            ->value('total');

        return Money::fromMinorUnits((int) $total);
    }

    /**
     * Where this account stands, as other modules may see it.
     */
    public function toStanding(): AccountStanding
    {
        return new AccountStanding(
            customerId: $this->customer_id,
            outstanding: $this->outstanding,
            creditLimit: $this->credit_limit,
        );
    }

    /**
     * Whether the customer currently owes the shop anything.
     */
    public function isInDebt(): bool
    {
        return $this->outstanding->isPositive();
    }

    /**
     * Whether the shop is holding money for this customer.
     */
    public function isInCredit(): bool
    {
        return $this->outstanding->isNegative();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'outstanding' => MoneyCast::class,
            'credit_limit' => MoneyCast::class,
        ];
    }
}
