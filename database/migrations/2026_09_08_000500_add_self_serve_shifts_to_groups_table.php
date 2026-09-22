<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The self-serve shifts switch (#582, ADR-0026 §1). Whether a Member who passes both sign-up
 * floors may author their own Shift in this Group's published Schedules, creating their Sign-up
 * in the same action. Off by default (ADR-0015: opt-in); the demo seeder turns it on for Gallery
 * Interpreters. Edited alongside {@see self_serve_unit_minutes} through the one Scheduler/Chair-
 * gated settings endpoint. Its own migration in the established one-column-per-file pattern.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('groups', function (Blueprint $table) {
            $table->boolean('self_serve_shifts')->default(false)->after('empty_desk_days_ahead');
        });
    }

    public function down(): void
    {
        Schema::table('groups', function (Blueprint $table) {
            $table->dropColumn('self_serve_shifts');
        });
    }
};
