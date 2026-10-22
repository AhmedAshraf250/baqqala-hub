<?php

namespace App\Modules\Accounts\Domain\Queries;

use App\Foundation\Money\Money;

/**
 * A statement's figures over a period — or over the account's whole life:
 * where it stood before and after, and what was taken, paid, brought from the
 * paper book, and forgiven in between.
 *
 * Voided entries and the reversals that voided them are left out, so a
 * mistake does not swell what was taken and paid; what corrected an entry is
 * counted as it now stands. `openingBalances` is signed: owed is above zero.
 */
final readonly class StatementTotals
{
    public function __construct(
        public Money $opening,
        public Money $closing,
        public Money $taken,
        public int $charges,
        public Money $paid,
        public int $payments,
        public Money $openingBalances,
        public Money $discounts,
        public int $entries,
        public int $voided,
    ) {}
}
