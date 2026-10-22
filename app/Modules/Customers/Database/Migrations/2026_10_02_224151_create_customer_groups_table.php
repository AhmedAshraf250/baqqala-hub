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
        Schema::create('customer_groups', function (Blueprint $table) {
            $table->id();
            // What the chip says. Renaming a group touches no customer.
            $table->string('name', 60);
            // One of GroupColor's eight keys, never a free colour: each is
            // tuned to stay readable in light and dark.
            $table->string('color', 20);
            // A Bootstrap Icons name without its `bi-` prefix, if any.
            $table->string('icon', 40)->nullable();
            // The owner's order for the chips.
            $table->unsignedSmallInteger('sort_order')->default(0);
            // Archived instead of deleted: hidden from the chips and choices,
            // its memberships kept for the day it is restored.
            $table->timestamp('archived_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_groups');
    }
};
