<?php

namespace App\Modules\Reports\Domain\Queries;

use App\Foundation\Identity\Models\AdminUser;
use App\Foundation\Money\Money;
use App\Foundation\Time\LocalClock;
use App\Modules\Accounts\Contracts\Enums\CorrectionReason;
use App\Modules\Accounts\Contracts\Enums\EntryKind;
use Carbon\CarbonImmutable;

/**
 * What was put right in a period: every void, undo, and correction — by
 * reason, and one by one — with whose entry it was, and who put it right.
 */
final class CorrectionsReport
{
    public function __construct(private readonly LocalClock $clock) {}

    /**
     * How many of each reason.
     *
     * @return array<string, int>
     */
    public function byReason(ReportPeriod $period): array
    {
        $counts = Ledger::between($period->bounds($this->clock))
            ->where('t.kind', EntryKind::Reversal->value)
            ->groupBy('t.reason')
            ->selectRaw('t.reason AS reason, COUNT(*) AS total')
            ->pluck('total', 'reason');

        $byReason = [];

        foreach (CorrectionReason::cases() as $reason) {
            $byReason[$reason->value] = (int) ($counts[$reason->value] ?? 0);
        }

        return $byReason;
    }

    /**
     * Each reversal in the period, the latest first: the entry it took back,
     * for whom, why, by whom, and whether a replacement put it right.
     *
     * @return list<array{at: CarbonImmutable, by: string, recordedBy: string, customerId: int, customer: string, reason: CorrectionReason|null, kind: EntryKind, amount: Money, recordedAt: CarbonImmutable, corrected: bool, note: string|null}>
     */
    public function entries(ReportPeriod $period, ?CorrectionReason $reason = null, int $limit = 200): array
    {
        $rows = Ledger::between($period->bounds($this->clock))
            ->where('t.kind', EntryKind::Reversal->value)
            ->when($reason !== null, static fn ($query) => $query->where('t.reason', $reason->value))
            ->join('account_transactions as o', 'o.id', '=', 't.reverses_id')
            ->join('customer_accounts as a', 'a.id', '=', 't.account_id')
            ->join('customers as c', 'c.id', '=', 'a.customer_id')
            ->orderByDesc('t.created_at')
            ->orderByDesc('t.id')
            ->limit($limit)
            ->selectRaw(
                't.created_at, t.created_by, t.reason, t.description, o.kind AS original_kind, o.amount AS original_amount,'
                .' o.created_at AS original_at, o.created_by AS original_by, a.customer_id, c.name AS customer,'
                .' EXISTS (SELECT 1 FROM account_transactions AS r WHERE r.replaces_id = o.id) AS corrected',
            )
            ->get();

        $names = AdminUser::query()
            ->whereKey($rows->pluck('created_by')->merge($rows->pluck('original_by'))->unique()->all())
            ->pluck('name', 'id');

        return array_values($rows->map(static fn (object $row): array => [
            'at' => CarbonImmutable::parse($row->created_at, 'UTC'),
            'by' => (string) ($names[$row->created_by] ?? '—'),
            'recordedBy' => (string) ($names[$row->original_by] ?? '—'),
            'customerId' => (int) $row->customer_id,
            'customer' => (string) $row->customer,
            'reason' => CorrectionReason::tryFrom((string) $row->reason),
            'kind' => EntryKind::from((string) $row->original_kind),
            'amount' => Money::fromMinorUnits((int) $row->original_amount),
            'recordedAt' => CarbonImmutable::parse($row->original_at, 'UTC'),
            'corrected' => (bool) $row->corrected,
            'note' => $row->description === null ? null : (string) $row->description,
        ])->all());
    }
}
