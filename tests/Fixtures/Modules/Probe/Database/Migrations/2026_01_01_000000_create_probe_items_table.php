<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('probe_items', function (Blueprint $table) {
            $table->id();
            $table->string('receipt');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('probe_items');
    }
};
