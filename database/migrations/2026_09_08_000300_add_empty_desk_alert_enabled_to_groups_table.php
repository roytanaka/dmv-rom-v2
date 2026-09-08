<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The empty-desk alert switch (#487, ADR-0024 §7). Whether a Group runs the every-third-day
 * alert that tells its roster which watched Shifts still have nobody. Off by default (ADR-0015:
 * opt-in); the demo seeder turns it on for Visitor Guides. Edited alongside
 * {@see empty_desk_days_ahead} and the shift-kind watch flags through the one Scheduler/Chair-
 * gated settings endpoint. Its own migration in the established one-column-per-file pattern.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('groups', function (Blueprint $table) {
            $table->boolean('empty_desk_alert_enabled')->default(false)->after('reminder_lead_days');
        });
    }

    public function down(): void
    {
        Schema::table('groups', function (Blueprint $table) {
            $table->dropColumn('empty_desk_alert_enabled');
        });
    }
};
