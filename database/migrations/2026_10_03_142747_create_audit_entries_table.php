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
        // Who changed what, and when — for everything but money, which the
        // ledger already keeps entry by entry. Written once, never edited.
        Schema::create('audit_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            // What it is about, namespaced by the layer that owns it —
            // `catalog.product`, `accounts.account` — so a module's entries
            // leave with it on uninstall.
            $table->string('subject', 64);
            $table->unsignedBigInteger('subject_id')->nullable();
            // Its name when it changed: a name can change later, the log
            // should still read as it did.
            $table->string('label');
            // A translation key in the owning layer's strings: «منتج», «حساب».
            $table->string('kind');
            $table->string('action', 16);
            // What changed, field by field. Not `changes`: Eloquent keeps a
            // property by that name for its own use.
            $table->json('fields')->nullable();
            $table->timestamp('created_at')->index();

            $table->index(['subject', 'subject_id']);
            $table->index('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_entries');
    }
};
