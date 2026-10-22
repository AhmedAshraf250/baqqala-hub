<?php

namespace App\Modules\Accounts\Domain\Queries;

use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * What is taken most, read from the lines of the charges themselves — never
 * kept by hand: what one customer usually takes, for the drawer's
 * suggestions, and what the shop sells most, for a customer with no history
 * yet and to rank the picker's results.
 *
 * A charge that was voided does not count: it was never taken.
 */
final class PopularProducts
{
    /**
     * The products a customer took most often lately, the most first.
     *
     * @return list<int>
     */
    public function forAccount(int $accountId, int $days = 90, int $limit = 6): array
    {
        return $this->ranked($this->lines($days)->where('t.account_id', $accountId), $limit);
    }

    /**
     * The products taken most often lately across the shop, the most first.
     *
     * @return list<int>
     */
    public function inShop(int $days = 30, int $limit = 30): array
    {
        return $this->ranked($this->lines($days), $limit);
    }

    private function lines(int $days): Builder
    {
        return DB::table('account_transaction_items as i')
            ->join('account_transactions as t', 't.id', '=', 'i.transaction_id')
            ->whereNotNull('i.product_id')
            ->where('t.created_at', '>=', CarbonImmutable::now()->subDays($days))
            ->whereNotExists(static fn (Builder $reversal) => $reversal
                ->from('account_transactions as r')
                ->whereColumn('r.reverses_id', 't.id'));
    }

    /**
     * @return list<int>
     */
    private function ranked(Builder $lines, int $limit): array
    {
        $ids = $lines
            ->groupBy('i.product_id')
            ->orderByRaw('COUNT(*) DESC')
            ->orderBy('i.product_id')
            ->limit($limit)
            ->pluck('i.product_id')
            ->all();

        return array_values(array_map(static fn (mixed $id): int => (int) $id, $ids));
    }
}
