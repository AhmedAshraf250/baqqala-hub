<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            // A Bootstrap Icons name for the category's tile in the product
            // picker, without the `bi-` prefix.
            $table->string('icon', 40)->nullable()->after('name');

            // The order the owner wants the categories in, everywhere they
            // are listed.
            $table->unsignedSmallInteger('sort_order')->default(0)->after('icon');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn(['icon', 'sort_order']);
        });
    }
};
