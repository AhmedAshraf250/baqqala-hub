<?php

namespace App\Modules\Reports\Domain\Queries;

use App\Foundation\Money\Money;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Who owes, by how long they have gone without paying: under a month, one to
 * two, two to three, and longer — and the list to ring, oldest first.
 *
 * «Without paying» runs from the later of the last payment and the start of
 * the debt, as the customer list's «late» does: a payment made before this
 * debt began does not make it recent.
 */
final class AgingReport
{
    /** Each bucket's days since a payment, from and to (null: no end). */
    public const array BUCKETS = [
        'under_30' => [0, 30],
        'under_60' => [31, 60],
        'under_90' => [61, 90],
        'over_90' => [91, null],
    ];

    private const string UNPAID_SINCE = '(CASE WHEN a.last_payment_at IS NULL OR a.last_payment_at < a.in_debt_since THEN a.in_debt_since ELSE a.last_payment_at END)';

    /**
     * How many owe in each bucket, and how much.
     *
     * @return array<string, array{count: int, owed: Money}>
     */
    public function buckets(): array
    {
        $now = CarbonImmutable::now();
        $columns = [];
        $bindings = [];

        foreach (self::BUCKETS as $bucket => [$from, $to]) {
            [$condition, $values] = $this->within($now, $from, $to);
            $columns[] = "COALESCE(SUM(CASE WHEN {$condition} THEN 1 ELSE 0 END), 0) AS {$bucket}_count";
            $columns[] = "COALESCE(SUM(CASE WHEN {$condition} THEN a.outstanding ELSE 0 END), 0) AS {$bucket}_owed";
            array_push($bindings, ...$values, ...$values);
        }

        $sums = $this->owing()->selectRaw(implode(', ', $columns), $bindings)->first();

        $buckets = [];

        foreach (array_keys(self::BUCKETS) as $bucket) {
            $buckets[$bucket] = [
                'count' => (int) ($sums->{"{$bucket}_count"} ?? 0),
                'owed' => Money::fromMinorUnits((int) ($sums->{"{$bucket}_owed"} ?? 0)),
            ];
        }

        return $buckets;
    }

    /**
     * The customers to ring: in one bucket, or all who owe — the longest
     * without paying first.
     *
     * @return list<array{customerId: int, name: string, phone: string|null, owed: Money, unpaidSince: CarbonImmutable|null, debtSince: CarbonImmutable|null, lastPayment: CarbonImmutable|null}>
     */
    public function customers(?string $bucket, int $limit = 200): array
    {
        $query = $this->owing()->join('customers as c', 'c.id', '=', 'a.customer_id');

        if ($bucket !== null && array_key_exists($bucket, self::BUCKETS)) {
            [$from, $to] = self::BUCKETS[$bucket];
            [$condition, $values] = $this->within(CarbonImmutable::now(), $from, $to);
            $query->whereRaw($condition, $values);
        }

        return array_values($query
            ->orderByRaw(self::UNPAID_SINCE)
            ->limit($limit)
            ->selectRaw('a.customer_id, c.name, c.phone, a.outstanding, a.in_debt_since, a.last_payment_at, '.self::UNPAID_SINCE.' AS unpaid_since')
            ->get()
            ->map(static fn (object $row): array => [
                'customerId' => (int) $row->customer_id,
                'name' => (string) $row->name,
                'phone' => $row->phone === null ? null : (string) $row->phone,
                'owed' => Money::fromMinorUnits((int) $row->outstanding),
                'unpaidSince' => $row->unpaid_since === null ? null : CarbonImmutable::parse($row->unpaid_since, 'UTC'),
                'debtSince' => $row->in_debt_since === null ? null : CarbonImmutable::parse($row->in_debt_since, 'UTC'),
                'lastPayment' => $row->last_payment_at === null ? null : CarbonImmutable::parse($row->last_payment_at, 'UTC'),
            ])
            ->all());
    }

    private function owing(): Builder
    {
        return DB::table('customer_accounts as a')->where('a.outstanding', '>', 0);
    }

    /**
     * The condition for «unpaid for between `$from` and `$to` days», with its
     * values: the moments those days reach back to.
     *
     * @return array{0: literal-string, 1: list<string>}
     */
    private function within(CarbonImmutable $now, int $from, ?int $to): array
    {
        $since = self::UNPAID_SINCE;
        $notBefore = $to === null ? null : $now->subDays($to + 1)->toDateTimeString();
        $notAfter = $now->subDays($from)->toDateTimeString();

        return $notBefore === null
            ? ["{$since} <= ?", [$notAfter]]
            : ["{$since} > ? AND {$since} <= ?", [$notBefore, $notAfter]];
    }
}
