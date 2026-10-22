<?php

namespace App\Modules\Accounts\Domain\Queries;

use App\Foundation\Identity\Models\AdminUser;
use App\Foundation\Money\Money;
use App\Foundation\Time\LocalClock;
use App\Modules\Accounts\Contracts\Enums\CorrectionReason;
use App\Modules\Accounts\Contracts\Enums\EntryKind;
use App\Modules\Accounts\Contracts\Enums\TransactionType;
use App\Modules\Accounts\Database\Models\AccountTransaction;
use App\Modules\Accounts\Database\Models\CustomerAccount;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * An account's statement: its entries newest first, grouped by business day,
 * and the figures over the same stretch of time.
 *
 * Nothing is worked out from the start of the ledger: every entry carries the
 * balance after it, so a page of fifty entries out of five thousand reads
 * fifty rows, and the balance before a period is the one after the entry just
 * before it. Entries are read a page at a time; «show older» asks for more.
 */
final readonly class AccountStatement
{
    public function __construct(private LocalClock $clock) {}

    /**
     * The newest entries within the bounds, grouped by business day, and
     * whether there are older ones.
     *
     * @param  array{0: CarbonImmutable|null, 1: CarbonImmutable|null}  $bounds
     * @return array{days: list<StatementDay>, older: bool}
     */
    public function days(CustomerAccount $account, array $bounds, ?StatementKind $kind = null, int $limit = 50): array
    {
        $query = AccountTransaction::query()
            ->where('account_id', $account->getKey())
            ->with(['items', 'reversal', 'reversed', 'replaced'])
            ->withExists('replacement')
            ->orderByDesc('id');

        $this->within($query, $bounds);
        $kind?->narrow($query);

        $entries = $query->limit($limit + 1)->get();
        $older = $entries->count() > $limit;
        $entries = $entries->take($limit)->values();

        $names = $this->names($entries);
        $rows = [];
        $closings = [];
        $folded = [];

        foreach ($entries as $index => $entry) {
            $date = $this->clock->businessDate($entry->created_at)->format('Y-m-d');
            $closings[$date] ??= $entry->outstanding_after;

            if (isset($folded[$entry->getKey()])) {
                continue;
            }

            // Undone at once: the reversal, and right after it the entry it
            // cancels — one grey line, the original's, marked undone.
            $next = $entries->get($index + 1);

            if ($kind === null && $entry->kind === EntryKind::Reversal && $entry->reason === CorrectionReason::Undo && $next?->getKey() === $entry->reverses_id) {
                $folded[$next->getKey()] = true;
                $rows[$date][] = $this->row($next, $names, undone: [
                    'at' => CarbonImmutable::instance($entry->created_at),
                    'by' => $names[$entry->created_by] ?? '—',
                    'seconds' => (int) CarbonImmutable::instance($next->created_at)->diffInSeconds($entry->created_at),
                ]);

                continue;
            }

            $rows[$date][] = $this->row($entry, $names);
        }

        $days = [];

        foreach ($rows as $date => $dayRows) {
            $days[] = new StatementDay(
                date: CarbonImmutable::createFromFormat('!Y-m-d', $date, $this->clock->timezone()) ?: CarbonImmutable::now(),
                entries: $dayRows,
                closing: $kind === null ? $closings[$date] : null,
            );
        }

        return ['days' => $days, 'older' => $older];
    }

    /**
     * The figures within the bounds, or over the account's whole life when
     * both are open.
     *
     * @param  array{0: CarbonImmutable|null, 1: CarbonImmutable|null}  $bounds
     */
    public function totals(CustomerAccount $account, array $bounds = [null, null]): StatementTotals
    {
        $entries = AccountTransaction::query()
            ->where('account_id', $account->getKey())
            ->where('kind', '!=', EntryKind::Reversal);

        $this->within($entries, $bounds);

        $standing = (clone $entries)->whereDoesntHave('reversal');

        $sums = $standing->toBase()->selectRaw(
            'COALESCE(SUM(CASE WHEN kind = ? THEN amount ELSE 0 END), 0) AS taken,'
            .' COALESCE(SUM(CASE WHEN kind = ? THEN 1 ELSE 0 END), 0) AS charges,'
            .' COALESCE(SUM(CASE WHEN kind = ? THEN amount ELSE 0 END), 0) AS paid,'
            .' COALESCE(SUM(CASE WHEN kind = ? THEN 1 ELSE 0 END), 0) AS payments,'
            .' COALESCE(SUM(CASE WHEN kind = ? THEN (CASE WHEN type = ? THEN amount ELSE -amount END) ELSE 0 END), 0) AS opening_balances,'
            .' COALESCE(SUM(CASE WHEN kind = ? THEN amount ELSE 0 END), 0) AS discounts',
            [
                EntryKind::Charge->value, EntryKind::Charge->value,
                EntryKind::Payment->value, EntryKind::Payment->value,
                EntryKind::OpeningBalance->value, TransactionType::Debit->value,
                EntryKind::Discount->value,
            ],
        )->first();

        [$start, $end] = $bounds;
        $opening = $start === null ? Money::zero() : $this->balanceBefore($account, $start);

        return new StatementTotals(
            opening: $opening,
            closing: $end === null ? $account->outstanding : $this->balanceBefore($account, $end, $opening),
            taken: Money::fromMinorUnits((int) ($sums->taken ?? 0)),
            charges: (int) ($sums->charges ?? 0),
            paid: Money::fromMinorUnits((int) ($sums->paid ?? 0)),
            payments: (int) ($sums->payments ?? 0),
            openingBalances: Money::fromMinorUnits((int) ($sums->opening_balances ?? 0)),
            discounts: Money::fromMinorUnits((int) ($sums->discounts ?? 0)),
            entries: (clone $entries)->count(),
            voided: (clone $entries)->whereHas('reversal')->count(),
        );
    }

    /**
     * The balance just before a moment: the one after the last entry before
     * it, or what is given when there was none.
     */
    private function balanceBefore(CustomerAccount $account, CarbonImmutable $moment, ?Money $none = null): Money
    {
        $last = AccountTransaction::query()
            ->where('account_id', $account->getKey())
            ->where('created_at', '<', $moment)
            ->orderByDesc('id')
            ->first(['outstanding_after']);

        return $last->outstanding_after ?? $none ?? Money::zero();
    }

    /**
     * @param  Builder<AccountTransaction>  $query
     * @param  array{0: CarbonImmutable|null, 1: CarbonImmutable|null}  $bounds
     */
    private function within(Builder $query, array $bounds): void
    {
        [$start, $end] = $bounds;

        $query->when($start !== null, static fn (Builder $query) => $query->where('created_at', '>=', $start))
            ->when($end !== null, static fn (Builder $query) => $query->where('created_at', '<', $end));
    }

    /**
     * Who recorded each entry on the page, and each reversal of one, in one
     * query.
     *
     * @param  Collection<int, AccountTransaction>  $entries
     * @return array<int, string>
     */
    private function names(Collection $entries): array
    {
        $ids = $entries->pluck('created_by')
            ->merge($entries->map(static fn (AccountTransaction $entry): ?int => $entry->reversal?->created_by))
            ->filter()
            ->unique()
            ->values()
            ->all();

        return AdminUser::query()->whereKey($ids)->pluck('name', 'id')->map(static fn (mixed $name): string => (string) $name)->all();
    }

    /**
     * @param  array<int, string>  $names
     * @param  array{at: CarbonImmutable, by: string, seconds: int}|null  $undone
     */
    private function row(AccountTransaction $entry, array $names, ?array $undone = null): StatementEntry
    {
        $reversal = $entry->reversal;

        return new StatementEntry(
            id: $entry->getKey(),
            reference: $entry->reference,
            kind: $entry->kind,
            type: $entry->type,
            amount: $entry->amount,
            before: $entry->outstandingBefore(),
            after: $entry->outstanding_after,
            at: CarbonImmutable::instance($entry->created_at),
            by: $names[$entry->created_by] ?? '—',
            note: $entry->description,
            reason: $entry->reason,
            lines: array_values($entry->items->pluck('name')->map(static fn (mixed $name): string => (string) $name)->all()),
            voided: $reversal === null ? null : [
                'at' => CarbonImmutable::instance($reversal->created_at),
                'by' => $names[$reversal->created_by] ?? '—',
                'reason' => $reversal->reason,
            ],
            cancels: $entry->reversed === null ? null : ['id' => $entry->reversed->getKey(), 'at' => CarbonImmutable::instance($entry->reversed->created_at)],
            corrects: $entry->replaced === null ? null : ['id' => $entry->replaced->getKey(), 'at' => CarbonImmutable::instance($entry->replaced->created_at)],
            corrected: (bool) $entry->getAttribute('replacement_exists'),
            undone: $undone,
        );
    }
}
