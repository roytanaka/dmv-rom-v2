<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The self-serve unit length (#582, ADR-0026 §2). How many minutes one unit runs: a self-serve
 * Shift's length is a count of units, and its `ends_at` is the start plus units × this. Forty-five
 * by default — the number every Gallery Interpreter knows, and what GI is seeded with. Edited
 * alongside {@see self_serve_shifts} through the one Scheduler/Chair-gated settings endpoint.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('groups', function (Blueprint $table) {
            $table->unsignedInteger('self_serve_unit_minutes')->default(45)->after('self_serve_shifts');
        });
    }

    public function down(): void
    {
        Schema::table('groups', function (Blueprint $table) {
            $table->dropColumn('self_serve_unit_minutes');
        });
    }
};
