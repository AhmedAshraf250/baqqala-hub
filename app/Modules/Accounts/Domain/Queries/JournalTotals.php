<?php

namespace App\Modules\Accounts\Domain\Queries;

use App\Foundation\Money\Money;

/**
 * A day's figures, or one person's share of them: what was taken on account
 * and how often, what was paid in and how often, and how many entries were
 * voided. Voided entries and their reversals count in none of the sums.
 */
final readonly class JournalTotals
{
    public function __construct(
        public Money $taken,
        public int $charges,
        public Money $collected,
        public int $payments,
        public int $voided,
    ) {}

    /**
     * How much what customers owe moved over the day: up when more was taken
     * than paid.
     */
    public function change(): Money
    {
        return $this->taken->minus($this->collected);
    }

    public function entries(): int
    {
        return $this->charges + $this->payments;
    }
}
