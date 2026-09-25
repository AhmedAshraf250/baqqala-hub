<?php

namespace App\Modules\Accounts\Contracts\Exceptions;

use App\Foundation\Money\Money;
use RuntimeException;

/**
 * Thrown when an entry would take an account past what it may owe.
 *
 * Published with the ledger's contract because it is part of it: a module that
 * posts through `AccountLedgerInterface` catches this. It carries the facts a
 * caller needs to explain the refusal, never the account record itself.
 */
final class CreditLimitExceeded extends RuntimeException
{
    public function __construct(
        public readonly int $customerId,
        public readonly Money $attemptedOutstanding,
        public readonly Money $creditLimit,
    ) {
        parent::__construct(sprintf(
            'This entry would take the account to %s, beyond its credit limit of %s.',
            $attemptedOutstanding->toDecimalString(),
            $creditLimit->toDecimalString(),
        ));
    }
}
