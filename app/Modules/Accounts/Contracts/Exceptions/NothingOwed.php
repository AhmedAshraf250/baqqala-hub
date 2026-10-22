<?php

namespace App\Modules\Accounts\Contracts\Exceptions;

use App\Foundation\Money\Money;
use RuntimeException;

/**
 * Thrown when a payment is to record only what is owed, and nothing is.
 */
final class NothingOwed extends RuntimeException
{
    public function __construct(
        public readonly int $customerId,
        public readonly Money $outstanding,
    ) {
        parent::__construct("Customer [{$customerId}] owes nothing; the account stands at {$outstanding->toDecimalString()}.");
    }
}
