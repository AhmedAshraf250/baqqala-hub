<?php

namespace App\Modules\Accounts\Domain\Queries;

use App\Foundation\Money\Money;
use App\Modules\Accounts\Contracts\Enums\CorrectionReason;
use App\Modules\Accounts\Contracts\Enums\EntryKind;
use App\Modules\Accounts\Contracts\Enums\TransactionType;
use Carbon\CarbonImmutable;

/**
 * One line of a statement, as it reads: what happened, made of what, who
 * recorded it and when, the amount in its column, and the balance either
 * side of it — with what became of it since: voided, or corrected.
 *
 * `undone` holds the reversal when the entry was undone at once — reversed
 * by the very next entry, with the reason «undone at once» — and the two read
 * as one grey line.
 */
final readonly class StatementEntry
{
    /**
     * @param  list<string>  $lines  The names of what it was made of, in order.
     * @param  array{at: CarbonImmutable, by: string, reason: CorrectionReason|null}|null  $voided
     * @param  array{id: int, at: CarbonImmutable}|null  $cancels  The entry a reversal cancels.
     * @param  array{id: int, at: CarbonImmutable}|null  $corrects  The entry this one is the corrected version of.
     * @param  array{at: CarbonImmutable, by: string, seconds: int}|null  $undone
     */
    public function __construct(
        public int $id,
        public string $reference,
        public EntryKind $kind,
        public TransactionType $type,
        public Money $amount,
        public Money $before,
        public Money $after,
        public CarbonImmutable $at,
        public string $by,
        public ?string $note,
        public ?CorrectionReason $reason,
        public array $lines,
        public ?array $voided = null,
        public ?array $cancels = null,
        public ?array $corrects = null,
        public bool $corrected = false,
        public ?array $undone = null,
    ) {}

    public function isDebit(): bool
    {
        return $this->type === TransactionType::Debit;
    }

    public function isVoided(): bool
    {
        return $this->voided !== null;
    }
}
