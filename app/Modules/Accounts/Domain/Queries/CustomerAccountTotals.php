<?php

namespace App\Modules\Accounts\Domain\Queries;

use App\Foundation\Money\Money;

/**
 * The one line of totals over whatever the list is narrowed to: how many,
 * how many owe and how much, how many the shop holds money for and how much,
 * how many are settled, and how many are late paying. Summed in the database.
 */
final readonly class CustomerAccountTotals
{
    public function __construct(
        public int $accounts,
        public int $owing,
        public Money $owed,
        public int $inCredit,
        public Money $heldForThem,
        public int $settled,
        public int $late = 0,
    ) {}

    public static function none(): self
    {
        return new self(0, 0, Money::zero(), 0, Money::zero(), 0);
    }
}
