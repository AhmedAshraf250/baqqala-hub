<?php

use App\Foundation\Text\PhoneNumber;
use App\Foundation\Text\SearchText;
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
        Schema::table('customers', function (Blueprint $table) {
            // The name folded by SearchText, so «احمد» finds «أحمد» and a
            // search compares plain strings. Written whenever the customer is
            // saved.
            $table->string('search_name')->default('')->index()->after('name');
        });

        // Customers stored before this have no folded name, and a phone as it
        // was typed. A phone whose digits another customer already has is left
        // as it was: two records of one person are for someone to merge, not
        // for a migration to guess between.
        DB::table('customers')->select(['id', 'name', 'phone'])->orderBy('id')->each(static function (object $customer): void {
            $phone = PhoneNumber::normalize($customer->phone);
            $taken = $phone !== null && DB::table('customers')->where('phone', $phone)->where('id', '!=', $customer->id)->exists();

            DB::table('customers')->where('id', $customer->id)->update([
                'search_name' => SearchText::normalize((string) $customer->name),
                'phone' => $taken ? $customer->phone : $phone,
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropIndex(['search_name']);
            $table->dropColumn('search_name');
        });
    }
};
