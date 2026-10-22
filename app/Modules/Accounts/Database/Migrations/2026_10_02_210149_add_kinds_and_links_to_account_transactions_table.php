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
        Schema::table('account_transactions', function (Blueprint $table) {
            // What happened — charge, payment, opening_balance, discount,
            // return, reversal — beside `type`, the direction it moved the
            // balance. «Opening balance» and «void» go either way, so neither
            // column can be derived from the other.
            $table->string('kind', 20)->default('charge')->after('type');

            // Why an entry was voided, corrected, or discounted, from a short
            // fixed list, so a report can count mistakes by kind.
            $table->string('reason', 30)->nullable()->after('description');

            // The entry this one cancels. Unique: an entry is voided once,
            // and the database refuses a second even from two clicks at once.
            $table->foreignId('reverses_id')->nullable()->unique()->after('reason')
                ->constrained('account_transactions')->restrictOnDelete();

            // The entry this one is the corrected version of — on this account,
            // or on the account it was moved to.
            $table->foreignId('replaces_id')->nullable()->after('reverses_id')
                ->constrained('account_transactions')->restrictOnDelete();

            // One save by a person, which may post more than one entry: a
            // charge and the part paid at once, a reversal and its
            // replacement. The same operation arriving twice posts once.
            $table->uuid('operation_id')->nullable()->index()->after('replaces_id');
        });

        // Before kinds, a debit was always goods taken and a credit money paid.
        DB::table('account_transactions')->where('type', 'credit')->update(['kind' => 'payment']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('account_transactions', function (Blueprint $table) {
            $table->dropForeign(['reverses_id']);
            $table->dropForeign(['replaces_id']);
            $table->dropUnique(['reverses_id']);
            $table->dropIndex(['operation_id']);
            $table->dropColumn(['kind', 'reason', 'reverses_id', 'replaces_id', 'operation_id']);
        });
    }
};
