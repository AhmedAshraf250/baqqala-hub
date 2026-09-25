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
        Schema::create('customer_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->unique()->constrained()->restrictOnDelete();
            // Money is stored as an integer number of minor units (piastres).
            //
            // `outstanding` is what the customer owes the shop: positive means
            // they are on the hook, negative means the shop holds their money.
            // It is a cached total of the ledger and is only ever written by
            // PostAccountTransaction.
            $table->bigInteger('outstanding')->default(0);

            // The most this account may owe. Zero means no ceiling configured.
            $table->bigInteger('credit_limit')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_accounts');
    }
};
