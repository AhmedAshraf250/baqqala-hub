<?php

namespace App\Modules\Reports\Domain\Queries;

use App\Foundation\Money\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * What customers owe now: in all, by the owner's groups, and who owes most —
 * read from each account's balance, which the ledger keeps current.
 */
final class DebtsReport
{
    /**
     * @return array{accounts: int, owing: int, owed: Money, inCredit: int, held: Money, settled: int}
     */
    public function totals(): array
    {
        $sums = DB::table('customer_accounts')->selectRaw(
            'COUNT(*) AS accounts,'
            .' COALESCE(SUM(CASE WHEN outstanding > 0 THEN 1 ELSE 0 END), 0) AS owing,'
            .' COALESCE(SUM(CASE WHEN outstanding > 0 THEN outstanding ELSE 0 END), 0) AS owed,'
            .' COALESCE(SUM(CASE WHEN outstanding < 0 THEN 1 ELSE 0 END), 0) AS in_credit,'
            .' COALESCE(SUM(CASE WHEN outstanding < 0 THEN -outstanding ELSE 0 END), 0) AS held,'
            .' COALESCE(SUM(CASE WHEN outstanding = 0 THEN 1 ELSE 0 END), 0) AS settled',
        )->first();

        return [
            'accounts' => (int) ($sums->accounts ?? 0),
            'owing' => (int) ($sums->owing ?? 0),
            'owed' => Money::fromMinorUnits((int) ($sums->owed ?? 0)),
            'inCredit' => (int) ($sums->in_credit ?? 0),
            'held' => Money::fromMinorUnits((int) ($sums->held ?? 0)),
            'settled' => (int) ($sums->settled ?? 0),
        ];
    }

    /**
     * Each group in use, and then the customers in none: how many are in it,
     * how many of them owe, and how much — the most owed first.
     *
     * @return list<array{name: string|null, color: string|null, members: int, owing: int, owed: Money}>
     */
    public function byGroup(): array
    {
        $figures = 'COUNT(*) AS members,'
            .' COALESCE(SUM(CASE WHEN a.outstanding > 0 THEN 1 ELSE 0 END), 0) AS owing,'
            .' COALESCE(SUM(CASE WHEN a.outstanding > 0 THEN a.outstanding ELSE 0 END), 0) AS owed';

        $groups = DB::table('customer_groups as g')
            ->join('customer_group_members as m', 'm.group_id', '=', 'g.id')
            ->join('customer_accounts as a', 'a.customer_id', '=', 'm.customer_id')
            ->whereNull('g.archived_at')
            ->groupBy('g.id', 'g.name', 'g.color')
            ->selectRaw("g.name AS name, g.color AS color, {$figures}")
            ->orderByDesc('owed')
            ->get();

        $alone = DB::table('customer_accounts as a')
            ->whereNotExists(static fn ($member) => $member->select(DB::raw(1))->from('customer_group_members as m')->whereColumn('m.customer_id', 'a.customer_id'))
            ->selectRaw($figures)
            ->first();

        $rows = $groups->map(static fn (object $row): array => [
            'name' => (string) $row->name,
            'color' => (string) $row->color,
            'members' => (int) $row->members,
            'owing' => (int) $row->owing,
            'owed' => Money::fromMinorUnits((int) $row->owed),
        ])->values()->all();

        if ((int) ($alone->members ?? 0) > 0) {
            $rows[] = ['name' => null, 'color' => null, 'members' => (int) $alone->members, 'owing' => (int) $alone->owing, 'owed' => Money::fromMinorUnits((int) $alone->owed)];
        }

        return array_values($rows);
    }

    /**
     * Who owes most, and since when.
     *
     * @return list<array{customerId: int, name: string, phone: string|null, owed: Money, since: CarbonImmutable|null, lastPayment: CarbonImmutable|null}>
     */
    public function largest(int $limit = 20): array
    {
        return array_values(DB::table('customer_accounts as a')
            ->join('customers as c', 'c.id', '=', 'a.customer_id')
            ->where('a.outstanding', '>', 0)
            ->orderByDesc('a.outstanding')
            ->limit($limit)
            ->get(['a.customer_id', 'c.name', 'c.phone', 'a.outstanding', 'a.in_debt_since', 'a.last_payment_at'])
            ->map(static fn (object $row): array => [
                'customerId' => (int) $row->customer_id,
                'name' => (string) $row->name,
                'phone' => $row->phone === null ? null : (string) $row->phone,
                'owed' => Money::fromMinorUnits((int) $row->outstanding),
                'since' => $row->in_debt_since === null ? null : CarbonImmutable::parse($row->in_debt_since, 'UTC'),
                'lastPayment' => $row->last_payment_at === null ? null : CarbonImmutable::parse($row->last_payment_at, 'UTC'),
            ])
            ->all());
    }
}
