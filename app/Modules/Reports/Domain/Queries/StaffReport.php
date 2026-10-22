<?php

namespace App\Modules\Reports\Domain\Queries;

use App\Foundation\Identity\Models\AdminUser;
use App\Foundation\Money\Money;
use App\Foundation\Time\LocalClock;
use App\Modules\Accounts\Contracts\Enums\CorrectionReason;
use App\Modules\Accounts\Contracts\Enums\EntryKind;

/**
 * Each person's share of a period: what they took on account, the cash they
 * collected, and the mistakes they put right — undone at once, voided, or
 * corrected.
 */
final class StaffReport
{
    public function __construct(private readonly LocalClock $clock) {}

    /**
     * Everyone who recorded anything in the period, the most cash first.
     *
     * @return list<array{id: int, name: string, charges: int, taken: Money, payments: int, collected: Money, undone: int, voided: int, corrected: int}>
     */
    public function people(ReportPeriod $period): array
    {
        $bounds = $period->bounds($this->clock);

        $figures = Ledger::standing(Ledger::between($bounds))
            ->groupBy('t.created_by')
            ->selectRaw(
                't.created_by AS person,'
                .' COALESCE(SUM(CASE WHEN t.kind = ? THEN 1 ELSE 0 END), 0) AS charges,'
                .' COALESCE(SUM(CASE WHEN t.kind = ? THEN t.amount ELSE 0 END), 0) AS taken,'
                .' COALESCE(SUM(CASE WHEN t.kind = ? THEN 1 ELSE 0 END), 0) AS payments,'
                .' COALESCE(SUM(CASE WHEN t.kind = ? THEN t.amount ELSE 0 END), 0) AS collected',
                [EntryKind::Charge->value, EntryKind::Charge->value, EntryKind::Payment->value, EntryKind::Payment->value],
            )
            ->get()
            ->keyBy('person');

        // A correction is a reversal and a replacement made together; a void
        // is a reversal alone; an undo is a void within moments.
        $fixes = Ledger::between($bounds)
            ->where('t.kind', EntryKind::Reversal->value)
            ->groupBy('t.created_by')
            ->selectRaw(
                't.created_by AS person,'
                .' COALESCE(SUM(CASE WHEN t.reason = ? THEN 1 ELSE 0 END), 0) AS undone,'
                .' COALESCE(SUM(CASE WHEN t.reason != ? AND NOT EXISTS (SELECT 1 FROM account_transactions AS r WHERE r.replaces_id = t.reverses_id) THEN 1 ELSE 0 END), 0) AS voided,'
                .' COALESCE(SUM(CASE WHEN EXISTS (SELECT 1 FROM account_transactions AS r WHERE r.replaces_id = t.reverses_id) THEN 1 ELSE 0 END), 0) AS corrected',
                [CorrectionReason::Undo->value, CorrectionReason::Undo->value],
            )
            ->get()
            ->keyBy('person');

        $ids = $figures->keys()->merge($fixes->keys())->unique()->map(static fn (mixed $id): int => (int) $id)->values()->all();
        $names = AdminUser::query()->whereKey($ids)->pluck('name', 'id');

        $people = array_map(static function (int $id) use ($figures, $fixes, $names): array {
            $figure = $figures->get($id);
            $fix = $fixes->get($id);

            return [
                'id' => $id,
                'name' => (string) ($names[$id] ?? '—'),
                'charges' => (int) ($figure->charges ?? 0),
                'taken' => Money::fromMinorUnits((int) ($figure->taken ?? 0)),
                'payments' => (int) ($figure->payments ?? 0),
                'collected' => Money::fromMinorUnits((int) ($figure->collected ?? 0)),
                'undone' => (int) ($fix->undone ?? 0),
                'voided' => (int) ($fix->voided ?? 0),
                'corrected' => (int) ($fix->corrected ?? 0),
            ];
        }, $ids);

        usort($people, static fn (array $a, array $b): int => $b['collected']->minorUnits <=> $a['collected']->minorUnits);

        return $people;
    }
}
