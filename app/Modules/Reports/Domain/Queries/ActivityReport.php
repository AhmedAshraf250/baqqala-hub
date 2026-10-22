<?php

namespace App\Modules\Reports\Domain\Queries;

use App\Foundation\Money\Money;
use App\Foundation\Time\LocalClock;
use App\Modules\Accounts\Contracts\Enums\EntryKind;

/**
 * What was taken on account and what was paid, day by day — or month by
 * month over a longer period — and what that did to what customers owe.
 *
 * Summed in one query: each entry is placed in its row by the moment its
 * business day (or month) began, so a sale at 01:30 on a shop that opens
 * past midnight counts towards the day it was made.
 */
final class ActivityReport
{
    public function __construct(private readonly LocalClock $clock) {}

    /**
     * @return list<array{label: string, taken: Money, charges: int, collected: Money, payments: int}>
     */
    public function rows(ReportPeriod $period): array
    {
        $steps = $period->steps($this->clock);
        $bucket = 'CASE';
        $bindings = [];

        // Each row's number, by the start of the row after it.
        foreach (array_slice($steps, 1) as $index => $step) {
            $bucket .= ' WHEN t.created_at < ? THEN ?';
            array_push($bindings, $step['start']->toDateTimeString(), $index);
        }

        $bucket .= ' ELSE ? END';
        $bindings[] = count($steps) - 1;

        $sums = Ledger::standing(Ledger::between($period->bounds($this->clock)))
            ->selectRaw(
                "{$bucket} AS step,"
                .' COALESCE(SUM(CASE WHEN t.kind = ? THEN t.amount ELSE 0 END), 0) AS taken,'
                .' COALESCE(SUM(CASE WHEN t.kind = ? THEN 1 ELSE 0 END), 0) AS charges,'
                .' COALESCE(SUM(CASE WHEN t.kind = ? THEN t.amount ELSE 0 END), 0) AS collected,'
                .' COALESCE(SUM(CASE WHEN t.kind = ? THEN 1 ELSE 0 END), 0) AS payments',
                [...$bindings, EntryKind::Charge->value, EntryKind::Charge->value, EntryKind::Payment->value, EntryKind::Payment->value],
            )
            ->groupBy('step')
            ->get()
            ->keyBy(static fn (object $row): int => (int) $row->step);

        $rows = [];

        foreach ($steps as $index => $step) {
            $row = $sums->get($index);

            $rows[] = [
                'label' => $step['label'],
                'taken' => Money::fromMinorUnits((int) ($row->taken ?? 0)),
                'charges' => (int) ($row->charges ?? 0),
                'collected' => Money::fromMinorUnits((int) ($row->collected ?? 0)),
                'payments' => (int) ($row->payments ?? 0),
            ];
        }

        return $rows;
    }

    /**
     * The period's figures in all.
     *
     * @return array{taken: Money, charges: int, collected: Money, payments: int}
     */
    public function totals(ReportPeriod $period): array
    {
        $sums = Ledger::standing(Ledger::between($period->bounds($this->clock)))
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN t.kind = ? THEN t.amount ELSE 0 END), 0) AS taken,'
                .' COALESCE(SUM(CASE WHEN t.kind = ? THEN 1 ELSE 0 END), 0) AS charges,'
                .' COALESCE(SUM(CASE WHEN t.kind = ? THEN t.amount ELSE 0 END), 0) AS collected,'
                .' COALESCE(SUM(CASE WHEN t.kind = ? THEN 1 ELSE 0 END), 0) AS payments',
                [EntryKind::Charge->value, EntryKind::Charge->value, EntryKind::Payment->value, EntryKind::Payment->value],
            )
            ->first();

        return [
            'taken' => Money::fromMinorUnits((int) ($sums->taken ?? 0)),
            'charges' => (int) ($sums->charges ?? 0),
            'collected' => Money::fromMinorUnits((int) ($sums->collected ?? 0)),
            'payments' => (int) ($sums->payments ?? 0),
        ];
    }
}
