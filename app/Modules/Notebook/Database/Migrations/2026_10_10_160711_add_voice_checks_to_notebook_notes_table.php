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
        Schema::table('notebook_notes', function (Blueprint $table) {
            // What a clip was when it was saved — its size and fingerprint,
            // read back from the disk — so a clip that went missing or was
            // damaged since is told apart from one that is whole. And how
            // loud it was at its loudest, 0 to 100, as the browser heard it.
            $table->unsignedInteger('voice_bytes')->nullable()->after('voice_seconds');
            $table->char('voice_sha256', 64)->nullable()->after('voice_bytes');
            $table->unsignedTinyInteger('voice_peak')->nullable()->after('voice_sha256');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('notebook_notes', function (Blueprint $table) {
            $table->dropColumn(['voice_bytes', 'voice_sha256', 'voice_peak']);
        });
    }
};
