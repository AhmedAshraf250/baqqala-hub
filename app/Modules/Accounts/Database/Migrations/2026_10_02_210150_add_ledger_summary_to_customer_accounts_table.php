<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // A summary of each account's ledger, kept beside `outstanding` and
        // written with it by the one action that posts, so the customer list
        // sorts and filters on plain columns. Every value can be recomputed
        // from the ledger, and `app:sync` checks that it still is.
        Schema::table('customer_accounts', function (Blueprint $table) {
            // The latest entry: the list's «last movement», and the version a
            // screen compares to notice a colleague posted meanwhile. A plain
            // column — a foreign key here would point back at the table that
            // points at this one.
            $table->unsignedBigInteger('last_entry_id')->nullable()->after('credit_limit');
            $table->timestamp('last_entry_at')->nullable()->index()->after('last_entry_id');

            // The latest payment not voided since: «last paid 47 days ago».
            $table->timestamp('last_payment_at')->nullable()->after('last_entry_at');

            // Since when the balance has been owed without falling to zero.
            $table->timestamp('in_debt_since')->nullable()->index()->after('last_payment_at');

            $table->index('outstanding');
        });

        $this->summariseExistingLedgers();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customer_accounts', function (Blueprint $table) {
            $table->dropIndex(['last_entry_at']);
            $table->dropIndex(['in_debt_since']);
            $table->dropIndex(['outstanding']);
            $table->dropColumn(['last_entry_id', 'last_entry_at', 'last_payment_at', 'in_debt_since']);
        });
    }

    /**
     * Accounts that already have entries get their summary now, by the same
     * rules the posting action keeps from here on.
     */
    private function summariseExistingLedgers(): void
    {
        DB::table('customer_accounts')->select(['id', 'outstanding'])->orderBy('id')->each(static function (object $account): void {
            $entries = DB::table('account_transactions')->where('account_id', $account->id);

            $last = (clone $entries)->orderByDesc('id')->first(['id', 'created_at']);

            if ($last === null) {
                return;
            }

            $lastSettled = (clone $entries)->where('outstanding_after', '<=', 0)->max('id');

            DB::table('customer_accounts')->where('id', $account->id)->update([
                'last_entry_id' => $last->id,
                'last_entry_at' => $last->created_at,
                'last_payment_at' => (clone $entries)->where('kind', 'payment')->max('created_at'),
                'in_debt_since' => $account->outstanding > 0
                    ? (clone $entries)->where('id', '>', $lastSettled ?? 0)->min('created_at')
                    : null,
            ]);
        });
    }
};
