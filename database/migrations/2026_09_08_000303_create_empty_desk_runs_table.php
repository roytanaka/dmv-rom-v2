<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The empty-desk run log (#487, ADR-0024 §7). One row per (Group, run date), written on every
 * cadence day the alert fires — silent or not — with the count of unstaffed watched Shifts it
 * found. The unique (group_id, run_date) grain is what makes "no run row for today" a database
 * fact: a second pass the same day finds the row and does nothing, and a missed cadence day is
 * not caught up. Rows are kept forever, like every other sent record (ADR-0024 §Schema).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('empty_desk_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained()->cascadeOnDelete();
            $table->date('run_date');
            $table->unsignedInteger('open_shift_count');
            $table->timestamps();

            $table->unique(['group_id', 'run_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('empty_desk_runs');
    }
};
