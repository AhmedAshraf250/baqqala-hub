<?php

namespace App\Modules\Reports\Domain\Queries;

use App\Foundation\Money\Money;
use App\Foundation\Time\LocalClock;
use App\Modules\Accounts\Contracts\Enums\EntryKind;
use Illuminate\Database\Query\Builder;

/**
 * What was taken on account, product by product and shelf by shelf: how
 * much of it, for how much, and on how many charges — from the lines of the
 * charges that stand.
 */
final class ProductsReport
{
    public function __construct(private readonly LocalClock $clock) {}

    /**
     * The products taken most, by what they came to; lines with no product
     * are one row of their own, last.
     *
     * @return list<array{productId: int|null, name: string|null, unit: string|null, category: string|null, quantity: int, amount: Money, charges: int}>
     */
    public function products(ReportPeriod $period, int $limit = 50): array
    {
        $products = $this->lines($period)
            ->whereNotNull('i.product_id')
            ->leftJoin('products as p', 'p.id', '=', 'i.product_id')
            ->leftJoin('categories as k', 'k.id', '=', 'p.category_id')
            ->groupBy('i.product_id', 'p.name', 'p.unit', 'k.name')
            ->selectRaw('i.product_id, p.name AS name, p.unit AS unit, k.name AS category, MAX(i.name) AS sold_as, COALESCE(SUM(i.quantity), 0) AS quantity, COALESCE(SUM(i.line_total), 0) AS amount, COUNT(DISTINCT t.id) AS charges')
            ->orderByDesc('amount')
            ->limit($limit)
            ->get()
            ->map(static fn (object $row): array => [
                'productId' => (int) $row->product_id,
                'name' => (string) ($row->name ?? $row->sold_as),
                'unit' => $row->unit === null ? null : (string) $row->unit,
                'category' => $row->category === null ? null : (string) $row->category,
                'quantity' => (int) $row->quantity,
                'amount' => Money::fromMinorUnits((int) $row->amount),
                'charges' => (int) $row->charges,
            ])
            ->values()
            ->all();

        $free = $this->lines($period)
            ->whereNull('i.product_id')
            ->selectRaw('COALESCE(SUM(i.line_total), 0) AS amount, COUNT(DISTINCT t.id) AS charges, COUNT(*) AS line_count')
            ->first();

        if ((int) ($free->line_count ?? 0) > 0) {
            $products[] = ['productId' => null, 'name' => null, 'unit' => null, 'category' => null, 'quantity' => 0, 'amount' => Money::fromMinorUnits((int) $free->amount), 'charges' => (int) $free->charges];
        }

        return array_values($products);
    }

    /**
     * What each shelf came to, the most first.
     *
     * @return list<array{category: string|null, amount: Money, lines: int}>
     */
    public function categories(ReportPeriod $period): array
    {
        return array_values($this->lines($period)
            ->leftJoin('products as p', 'p.id', '=', 'i.product_id')
            ->leftJoin('categories as k', 'k.id', '=', 'p.category_id')
            ->groupBy('k.id', 'k.name')
            ->selectRaw('k.name AS category, COALESCE(SUM(i.line_total), 0) AS amount, COUNT(*) AS line_count')
            ->orderByDesc('amount')
            ->get()
            ->map(static fn (object $row): array => [
                'category' => $row->category === null ? null : (string) $row->category,
                'amount' => Money::fromMinorUnits((int) $row->amount),
                'lines' => (int) $row->line_count,
            ])
            ->all());
    }

    /**
     * The lines of the period's charges that stand, as `i`, with their charge
     * as `t`.
     */
    private function lines(ReportPeriod $period): Builder
    {
        return Ledger::standing(Ledger::between($period->bounds($this->clock)))
            ->where('t.kind', EntryKind::Charge->value)
            ->join('account_transaction_items as i', 'i.transaction_id', '=', 't.id');
    }
}
