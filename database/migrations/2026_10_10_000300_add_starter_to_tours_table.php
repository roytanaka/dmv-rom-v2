<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A starter Tour (#793, ADR-0033 §7): a Tour a Member gets when their standing becomes Full
     * ("Museum Highlights" for Docents). A per-Group setting on the Tours, never a hard-coded id.
     */
    public function up(): void
    {
        Schema::table('tours', function (Blueprint $table) {
            $table->boolean('starter')->default(false)->after('open_to_all');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tours', function (Blueprint $table) {
            $table->dropColumn('starter');
        });
    }
};
