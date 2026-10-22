<?php

namespace App\Modules\Accounts\Domain\Queries;

use App\Foundation\Identity\Models\AdminUser;
use App\Foundation\Money\Money;
use App\Foundation\Time\LocalClock;
use App\Modules\Accounts\Contracts\Enums\EntryKind;
use App\Modules\Accounts\Contracts\Enums\TransactionType;
use App\Modules\Accounts\Database\Models\AccountTransaction;
use App\Modules\Accounts\Database\Models\AccountTransactionItem;
use App\Modules\Accounts\Database\Models\CustomerAccount;
use App\Modules\Customers\Contracts\CustomerRepositoryInterface;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * One business day's book: every entry posted in it for every customer, the
 * day's figures, and each person's share of them — above all the cash they
 * took, which is what the drawer is counted against at closing.
 *
 * The day follows the owner's zone and the hour the business day starts.
 * Every entry is listed, a voided one marked; the figures leave out voided
 * entries and the reversals that voided them, the way every total does.
 */
final class DailyJournal
{
    /** The most entries a day's page lists; a shop's day is far fewer. */
    public const int LIMIT = 500;

    public function __construct(
        private readonly LocalClock $clock,
        private readonly CustomerRepositoryInterface $customers,
    ) {}

    /**
     * The day's entries, the latest first, narrowed to one person's or one
     * kind's when asked.
     *
     * @return list<JournalEntry>
     */
    public function entries(CarbonImmutable $day, ?int $postedBy = null, ?EntryKind $kind = null): array
    {
        $entries = $this->onDay($day)
            ->when($postedBy !== null, static fn (Builder $query) => $query->where('created_by', $postedBy))
            ->when($kind !== null, static fn (Builder $query) => $query->where('kind', $kind))
            ->with(['items', 'reversal'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(self::LIMIT)
            ->get();

        $customerOf = CustomerAccount::query()
            ->whereKey($entries->pluck('account_id')->unique()->all())
            ->pluck('customer_id', 'id');
        $customers = $this->customers->findMany(array_values($customerOf->map(static fn (mixed $id): int => (int) $id)->all()));
        $names = $this->names(array_values($entries->pluck('created_by')->unique()->all()));

        return array_values($entries->map(static function (AccountTransaction $entry) use ($customerOf, $customers, $names): JournalEntry {
            $customerId = (int) $customerOf->get($entry->account_id);

            return new JournalEntry(
                id: (int) $entry->getKey(),
                customerId: $customerId,
                customer: $customers->get($customerId)->name ?? '—',
                kind: $entry->kind,
                debit: $entry->type === TransactionType::Debit,
                amount: $entry->amount,
                at: CarbonImmutable::instance($entry->created_at),
                by: $names[$entry->created_by] ?? '—',
                lines: array_values($entry->items->map(static fn (AccountTransactionItem $item): string => $item->name)->all()),
                note: $entry->description,
                voided: $entry->reversal !== null,
            );
        })->all());
    }

    /**
     * The day's figures.
     */
    public function totals(CarbonImmutable $day): JournalTotals
    {
        $sums = $this->figures($this->onDay($day))->first();

        return $this->totalsFrom($sums);
    }

    /**
     * Each person who recorded anything that day, with their share of its
     * figures, the most cash first.
     *
     * @return list<array{id: int, name: string, totals: JournalTotals}>
     */
    public function people(CarbonImmutable $day): array
    {
        $rows = $this->figures($this->onDay($day))
            ->addSelect('created_by')
            ->groupBy('created_by')
            ->get();

        $names = $this->names(array_values($rows->pluck('created_by')->all()));

        return array_values($rows
            ->map(fn (object $row): array => [
                'id' => (int) $row->created_by,
                'name' => $names[(int) $row->created_by] ?? '—',
                'totals' => $this->totalsFrom($row),
            ])
            ->sortByDesc(static fn (array $person): int => $person['totals']->collected->minorUnits)
            ->all());
    }

    /**
     * @return Builder<AccountTransaction>
     */
    private function onDay(CarbonImmutable $day): Builder
    {
        [$start, $end] = $this->clock->dayBounds($day);

        return AccountTransaction::query()
            ->where('created_at', '>=', $start)
            ->where('created_at', '<', $end);
    }

    /**
     * The sums over what stands, and the count of what was voided, in one
     * query.
     *
     * @param  Builder<AccountTransaction>  $entries
     */
    private function figures(Builder $entries): QueryBuilder
    {
        $voided = 'EXISTS (SELECT 1 FROM account_transactions AS undone WHERE undone.reverses_id = account_transactions.id)';
        $stands = "kind != '".EntryKind::Reversal->value."' AND NOT {$voided}";

        return $entries->toBase()->selectRaw(
            "COALESCE(SUM(CASE WHEN {$stands} AND kind = ? THEN amount ELSE 0 END), 0) AS taken,"
            ." COALESCE(SUM(CASE WHEN {$stands} AND kind = ? THEN 1 ELSE 0 END), 0) AS charges,"
            ." COALESCE(SUM(CASE WHEN {$stands} AND kind = ? THEN amount ELSE 0 END), 0) AS collected,"
            ." COALESCE(SUM(CASE WHEN {$stands} AND kind = ? THEN 1 ELSE 0 END), 0) AS payments,"
            ." COALESCE(SUM(CASE WHEN kind != '".EntryKind::Reversal->value."' AND {$voided} THEN 1 ELSE 0 END), 0) AS voided",
            [EntryKind::Charge->value, EntryKind::Charge->value, EntryKind::Payment->value, EntryKind::Payment->value],
        );
    }

    private function totalsFrom(?object $sums): JournalTotals
    {
        return new JournalTotals(
            taken: Money::fromMinorUnits((int) ($sums->taken ?? 0)),
            charges: (int) ($sums->charges ?? 0),
            collected: Money::fromMinorUnits((int) ($sums->collected ?? 0)),
            payments: (int) ($sums->payments ?? 0),
            voided: (int) ($sums->voided ?? 0),
        );
    }

    /**
     * Who recorded what, by id.
     *
     * @param  list<mixed>  $ids
     * @return array<int, string>
     */
    private function names(array $ids): array
    {
        return AdminUser::query()->whereKey($ids)->pluck('name', 'id')->map(static fn (mixed $name): string => (string) $name)->all();
    }
}
