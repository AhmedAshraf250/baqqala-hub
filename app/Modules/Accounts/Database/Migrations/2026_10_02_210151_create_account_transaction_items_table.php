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
        // What a charge was made of. A line is a product from the catalog or
        // a free line with a name and an amount; the lines of an entry add up
        // to its amount. Like the entry, a line is never edited.
        Schema::create('account_transaction_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->constrained('account_transactions')->restrictOnDelete();
            $table->unsignedSmallInteger('position');

            // Null on a free line. A product that was sold is archived, never
            // deleted, so the database refuses to delete it.
            $table->foreignId('product_id')->nullable()->constrained('products')->restrictOnDelete();

            // Copied from the product when it was sold, so the statement reads
            // the same after the catalog changes.
            $table->string('name');
            $table->string('unit', 10)->nullable();

            // Thousandths of the unit: two pieces are 2000, a quarter kilo 250.
            $table->bigInteger('quantity');

            // Money is stored as an integer number of minor units (piastres).
            // `unit_price` is what it was sold at; `list_price` what the
            // catalog asked at that moment, null on a free line. They differ
            // when the price was changed at the counter.
            $table->bigInteger('unit_price');
            $table->bigInteger('list_price')->nullable();
            $table->bigInteger('line_total');

            $table->timestamps();

            $table->index(['transaction_id', 'position']);
            $table->index('product_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('account_transaction_items');
    }
};
