<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The Reminder on/off switch (#486, ADR-0024 §7). One per-Group flag a Chair or Scheduler
 * edits from the Scheduling section: whether the daily pass writes a Reminder for each of
 * the Group's upcoming Sign-ups. Off by default (ADR-0015: scheduling is opt-in), turned on
 * for the five Groups that run Reminders today by the demo seeder. Meaningful only where the
 * Group runs scheduling; the settings endpoint is gated on that capability.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('groups', function (Blueprint $table) {
            $table->boolean('reminders_enabled')->default(false)->after('hours_multiplier');
        });
    }

    public function down(): void
    {
        Schema::table('groups', function (Blueprint $table) {
            $table->dropColumn('reminders_enabled');
        });
    }
};
