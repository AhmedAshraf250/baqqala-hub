<?php

namespace App\Modules\Accounts\Contracts\Data;

use App\Foundation\Money\Money;
use App\Modules\Accounts\Contracts\AccountLedgerInterface;

/**
 * Where a customer's account stands, as other modules see it.
 *
 * Read-only by construction. A module deciding whether to let a basket go on
 * account needs these facts and nothing else — certainly not the ability to
 * write to the ledger, which only {@see AccountLedgerInterface::post()} grants.
 */
final readonly class AccountStanding
{
    public function __construct(
        public int $customerId,
        public Money $outstanding,
        public Money $creditLimit,
    ) {}

    /**
     * Whether the customer owes the shop anything.
     */
    public function isInDebt(): bool
    {
        return $this->outstanding->isPositive();
    }

    /**
     * Whether a further amount would breach the ceiling.
     *
     * A credit limit of zero means no ceiling is configured, not no credit.
     */
    public function wouldBreachLimit(Money $amount): bool
    {
        return ! $this->creditLimit->isZero()
            && $this->outstanding->plus($amount)->isGreaterThan($this->creditLimit);
    }
}
