<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The Reminder lead time (#486, ADR-0024 §7). How many days ahead of a Shift the daily pass
 * sends its Reminder: the window runs from now to the end of today plus this many days. Three
 * by default — the department's standard, and what the five Reminder Groups are seeded with;
 * Invitations will set four when built. Edited alongside {@see reminders_enabled} through the
 * one Scheduler/Chair-gated settings endpoint.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('groups', function (Blueprint $table) {
            $table->unsignedInteger('reminder_lead_days')->default(3)->after('reminders_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('groups', function (Blueprint $table) {
            $table->dropColumn('reminder_lead_days');
        });
    }
};
