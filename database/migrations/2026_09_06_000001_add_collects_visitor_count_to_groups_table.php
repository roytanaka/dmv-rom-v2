<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Whether a Group collects a per-shift visitor count (#445, PRD #443, ADR-0023 §5). The
     * switch is a **Group** setting, not a shift-kind one: two Groups running the same kind
     * of shift may differ, so the practice sits where the switch sits.
     *
     * Named `collects_*` rather than `has_*` on purpose. The `has_*` flags switch whole
     * **sections** on, each with a tab and a route behind it; this switches a **field** on
     * inside the existing Scheduling section — the sign-out panel and its box — and nothing
     * about the Scheduling tab's existence depends on it. It implies `has_scheduling` (a
     * Group with no Sign-ups has nothing to hang a count on); that is seeded and asserted
     * rather than enforced as a database constraint, matching the other capability flags.
     *
     * Defaults false: a Group collects nothing until it is turned on. Reception stays off and
     * so is asked for nothing.
     */
    public function up(): void
    {
        Schema::table('groups', function (Blueprint $table) {
            $table->boolean('collects_visitor_count')->default(false)->after('has_announcements');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('groups', function (Blueprint $table) {
            $table->dropColumn('collects_visitor_count');
        });
    }
};
