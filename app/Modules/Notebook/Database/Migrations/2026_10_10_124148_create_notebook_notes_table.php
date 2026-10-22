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
        Schema::create('notebook_notes', function (Blueprint $table) {
            $table->id();

            // What was jotted in the rush: «أخذ» or «دفع», in words, by voice,
            // or both. Nothing here is money yet — a note moves no balance.
            $table->string('kind', 16)->default('charge');
            $table->text('body')->nullable();
            $table->string('voice_path')->nullable();
            $table->string('voice_type', 64)->nullable();
            $table->unsignedSmallInteger('voice_seconds')->nullable();
            $table->foreignId('written_by')->nullable()->constrained('users')->nullOnDelete();

            // `pending` until someone posts it to an account or throws it away.
            // `operation_id` is the save that will post it: the drawer carries
            // it, and the ledger's announcement of that save is how the note
            // learns it was posted. A new one is made whenever the note is
            // opened again, so a posting that was undone is never mistaken
            // for the next one.
            $table->string('status', 16)->default('pending')->index();
            $table->uuid('operation_id')->unique();

            // The account and the entry it was last posted as — kept when an
            // undo sends it back, so a correction can find it again.
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('entry_id')->nullable()->constrained('account_transactions')->nullOnDelete();
            $table->foreignId('settled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('settled_at')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notebook_notes');
    }
};
