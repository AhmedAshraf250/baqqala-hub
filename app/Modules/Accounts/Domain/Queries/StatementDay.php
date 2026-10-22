<?php

namespace App\Modules\Accounts\Domain\Queries;

use App\Foundation\Money\Money;
use Carbon\CarbonImmutable;

/**
 * One business day of a statement: its entries, latest first, and where the
 * account stood when the day was done — null when the statement is narrowed
 * to some kinds of entry, and the last one shown is not the day's last.
 */
final readonly class StatementDay
{
    /**
     * @param  list<StatementEntry>  $entries
     */
    public function __construct(
        public CarbonImmutable $date,
        public array $entries,
        public ?Money $closing,
    ) {}
}
