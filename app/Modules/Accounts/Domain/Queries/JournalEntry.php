<?php

namespace App\Modules\Accounts\Domain\Queries;

use App\Foundation\Money\Money;
use App\Modules\Accounts\Contracts\Enums\EntryKind;
use Carbon\CarbonImmutable;

/**
 * One line of a day's journal: when, for whom, what happened and for how
 * much, who recorded it, and whether it still stands.
 */
final readonly class JournalEntry
{
    /**
     * @param  list<string>  $lines  What a charge was made of, one product or line each.
     */
    public function __construct(
        public int $id,
        public int $customerId,
        public string $customer,
        public EntryKind $kind,
        public bool $debit,
        public Money $amount,
        public CarbonImmutable $at,
        public string $by,
        public array $lines,
        public ?string $note,
        public bool $voided,
    ) {}

    /**
     * Whether it counts: not voided, and not itself the reversal of another.
     */
    public function stands(): bool
    {
        return ! $this->voided && $this->kind !== EntryKind::Reversal;
    }
}
