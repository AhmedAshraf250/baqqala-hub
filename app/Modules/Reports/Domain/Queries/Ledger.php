<?php

namespace App\Modules\Reports\Domain\Queries;

use App\Modules\Accounts\Contracts\Enums\EntryKind;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * The accounts' ledger as the reports read it: straight from its table, the
 * one exception to modules meeting only through their contracts — a report
 * sums across modules in one query, and only ever reads.
 *
 * Every report counts what stands now: not a reversal, and not reversed. An
 * entry voided tomorrow leaves today's figures tomorrow — the reports show
 * what really happened, as best anyone knows it now.
 */
final class Ledger
{
    /**
     * The ledger's entries, as `t`, between two moments.
     *
     * @param  array{0: CarbonImmutable, 1: CarbonImmutable}  $bounds
     */
    public static function between(array $bounds): Builder
    {
        return DB::table('account_transactions as t')
            ->where('t.created_at', '>=', $bounds[0])
            ->where('t.created_at', '<', $bounds[1]);
    }

    /**
     * Only what stands.
     */
    public static function standing(Builder $entries): Builder
    {
        return $entries
            ->where('t.kind', '!=', EntryKind::Reversal->value)
            ->whereNotExists(static fn (Builder $undone) => $undone
                ->select(DB::raw(1))
                ->from('account_transactions as undone')
                ->whereColumn('undone.reverses_id', 't.id'));
    }
}
