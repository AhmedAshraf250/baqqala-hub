<?php

namespace App\Modules\Accounts\Domain\Services;

use App\Foundation\Contracts\Modules\SyncStepInterface;
use App\Foundation\Modules\ModuleServiceProvider;
use App\Foundation\Money\Money;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * Each account's balance and summary, against its ledger — for `app:sync`.
 *
 * The balance, the latest entry, the latest payment, and since when the debt
 * stands are all a cached view of the ledger, written with every entry by the
 * one action that posts. This recomputes each from the entries themselves, in
 * the database, and reports an account whose cache says otherwise; fixing
 * rewrites the cache from the ledger, which is the truth.
 *
 * It also reports an entry whose balance does not follow from the one before
 * it — which no fix can mend, because the entries are the record: something
 * changed the ledger outside the application, and a person has to find out
 * what.
 */
final readonly class ReconcileAccountTotals implements SyncStepInterface
{
    public function title(): string
    {
        return 'Account balances, against their ledgers';
    }

    public function findings(?ModuleServiceProvider $module = null): array
    {
        if (($module !== null && $module->key() !== 'accounts') || ! $this->ledgerIsMigrated()) {
            return [];
        }

        $findings = [];

        foreach ($this->brokenEntries() as $reference) {
            $findings[] = ["entry {$reference}", 'its balance does not follow from the entry before it'];
        }

        foreach ($this->staleAccounts() as $account) {
            $findings[] = ["account of customer {$account['customer_id']}", implode('; ', $account['wrong'])];
        }

        return $findings;
    }

    public function fix(?ModuleServiceProvider $module = null): void
    {
        if (! $this->ledgerIsMigrated()) {
            return;
        }

        foreach ($this->staleAccounts() as $account) {
            DB::table('customer_accounts')->where('id', $account['id'])->update($account['expected']);
        }

        $broken = $this->brokenEntries();

        if ($broken->isNotEmpty()) {
            throw new RuntimeException(sprintf(
                '%d ledger %s do not follow from the entry before them (%s). The ledger is the record, so recomputing cannot mend it: something changed it outside the application. Find out what before anything else posts to those accounts.',
                $broken->count(),
                $broken->count() === 1 ? 'entry' : 'entries',
                $broken->take(5)->implode(', '),
            ));
        }
    }

    /**
     * Whether the ledger has every column this step reads.
     *
     * On an installation just updated to this code they are not there yet:
     * the migrations that add them run in `app:sync`'s first step, which
     * reports them until then. Until they have run there is nothing to
     * compare, so this step finds nothing rather than fail on a missing
     * column — and after them, `app:sync` looks again before it fixes.
     */
    private function ledgerIsMigrated(): bool
    {
        return Schema::hasColumns('customer_accounts', ['outstanding', 'last_entry_id', 'last_entry_at', 'last_payment_at', 'in_debt_since'])
            && Schema::hasColumns('account_transactions', ['account_id', 'type', 'kind', 'amount', 'outstanding_after', 'reverses_id']);
    }

    /**
     * The references of entries whose balance before them is not the balance
     * after the one before, account by account in the order they were posted.
     *
     * @return Collection<int, string>
     */
    private function brokenEntries(): Collection
    {
        $chain = DB::table('account_transactions')->select([
            'id', 'reference', 'type', 'amount', 'outstanding_after',
            DB::raw('LAG(outstanding_after, 1, 0) OVER (PARTITION BY account_id ORDER BY id) AS previous_after'),
        ]);

        return DB::query()
            ->fromSub($chain, 'chain')
            ->whereRaw("outstanding_after - (CASE WHEN type = 'debit' THEN amount ELSE -amount END) <> previous_after")
            ->orderBy('id')
            ->pluck('reference');
    }

    /**
     * Accounts whose cached summary differs from their ledger, with what is
     * wrong and the values the ledger gives.
     *
     * @return list<array{id: int, customer_id: int, wrong: list<string>, expected: array<string, mixed>}>
     */
    private function staleAccounts(): array
    {
        // The account's own entries, under two aliases for the one subquery
        // that needs a second look at them.
        $t = 'account_transactions t WHERE t.account_id = a.id';
        $z = 'account_transactions z WHERE z.account_id = a.id';

        $rows = DB::table('customer_accounts', 'a')->select([
            'a.id', 'a.customer_id', 'a.outstanding', 'a.last_entry_id', 'a.last_entry_at', 'a.last_payment_at', 'a.in_debt_since',
            DB::raw("COALESCE((SELECT SUM(CASE WHEN t.type = 'debit' THEN t.amount ELSE -t.amount END) FROM {$t}), 0) AS ledger_outstanding"),
            DB::raw("(SELECT MAX(t.id) FROM {$t}) AS ledger_last_entry_id"),
            DB::raw("(SELECT t.created_at FROM {$t} ORDER BY t.id DESC LIMIT 1) AS ledger_last_entry_at"),
            DB::raw("(SELECT MAX(t.created_at) FROM {$t} AND t.kind = 'payment' AND NOT EXISTS (SELECT 1 FROM account_transactions r WHERE r.reverses_id = t.id)) AS ledger_last_payment_at"),
            DB::raw("(SELECT MIN(t.created_at) FROM {$t} AND t.id > COALESCE((SELECT MAX(z.id) FROM {$z} AND z.outstanding_after <= 0), 0)) AS ledger_debt_start"),
        ])->orderBy('a.id')->get();

        $stale = [];

        foreach ($rows as $row) {
            $expected = [
                'outstanding' => (int) $row->ledger_outstanding,
                'last_entry_id' => $row->ledger_last_entry_id === null ? null : (int) $row->ledger_last_entry_id,
                'last_entry_at' => $row->ledger_last_entry_at,
                'last_payment_at' => $row->ledger_last_payment_at,
                'in_debt_since' => (int) $row->ledger_outstanding > 0 ? $row->ledger_debt_start : null,
            ];

            $wrong = [];

            if ((int) $row->outstanding !== $expected['outstanding']) {
                $wrong[] = sprintf(
                    'balance %s, but its ledger adds up to %s',
                    Money::fromMinorUnits((int) $row->outstanding)->toDecimalString(),
                    Money::fromMinorUnits($expected['outstanding'])->toDecimalString(),
                );
            }

            foreach (['last_entry_id' => 'latest entry', 'last_entry_at' => 'latest entry time', 'last_payment_at' => 'latest payment', 'in_debt_since' => 'owed since'] as $column => $label) {
                if ((string) $row->{$column} !== (string) $expected[$column]) {
                    $wrong[] = "{$label} out of date";
                }
            }

            if ($wrong !== []) {
                $stale[] = [
                    'id' => (int) $row->id,
                    'customer_id' => (int) $row->customer_id,
                    'wrong' => $wrong,
                    'expected' => $expected,
                ];
            }
        }

        return $stale;
    }
}
