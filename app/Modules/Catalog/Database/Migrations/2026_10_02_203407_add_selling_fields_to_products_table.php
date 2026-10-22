<?php

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
        Schema::table('products', function (Blueprint $table) {
            // How the product is sold, and so how its quantity is typed: by the
            // piece, the kilo, or the litre. Replaces `attributes.unit`.
            $table->string('unit', 10)->default('piece')->after('name');

            // Other names the counter uses for it — a brand, a colloquial
            // name, its Latin spelling — so a search finds «بامبرز» or «chipsy».
            $table->string('search_terms')->nullable()->after('unit');

            // The name and the other names folded by SearchText, so a search
            // compares plain strings. Written whenever the product is saved.
            $table->string('search_name')->default('')->after('search_terms');

            // Empty until a scanner is used; the search already reads one.
            $table->string('barcode', 32)->nullable()->unique()->after('sku');
        });

        // Products stored before this have a name and no folded form yet.
        DB::table('products')->select(['id', 'name'])->orderBy('id')->each(static function (object $product): void {
            DB::table('products')->where('id', $product->id)->update([
                'search_name' => SearchText::normalize((string) $product->name),
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropUnique(['barcode']);
            $table->dropColumn(['unit', 'search_terms', 'search_name', 'barcode']);
        });
    }
};
