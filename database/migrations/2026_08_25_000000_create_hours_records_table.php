<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The Hours record (ADR-0022 §1) — one Member's hours in one Group for one calendar
     * month, at legacy's grain: (Member, Group, month, optional Meeting). It carries
     * `scheduled_hours` and `extra_hours` as whole integers plus a derived `total_hours`
     * their sum, kept in sync by the model rather than a database trigger (§1). There are
     * no dates, no durations, no descriptions — the month *is* the unit.
     *
     * `year_month` is the `YYYYMM` bucket stored as a string so it sorts lexically and
     * indexes for the range queries the reports run.
     *
     * The uniqueness grain includes the nullable Meeting, and a plain unique index treats
     * each SQL NULL as distinct — so it would *not* hold "one row per Member/Group/month"
     * for the ordinary no-meeting case, letting a Member accrue two competing rows for one
     * month. "No meeting" therefore needs a real single value, not a null: `meeting_id` is
     * a non-null column defaulting to the sentinel `0` (legacy's own `MeetingID NOT NULL
     * DEFAULT 0`), so the unique index holds identically on SQLite and MariaDB. A genuine
     * meeting reference is a non-zero id; meeting-hours entry is out of this pass (§2), so
     * only the importer ever writes a non-zero value. No foreign key is declared on it: the
     * sentinel `0` matches no `meetings` row, exactly as in legacy's trigger-driven table.
     */
    public function up(): void
    {
        Schema::create('hours_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained('members')->cascadeOnDelete();
            $table->foreignId('group_id')->constrained('groups')->cascadeOnDelete();
            // The YYYYMM month bucket — stored as a string so it sorts lexically and
            // range-indexes; the one unit the department has reported in since 2013.
            $table->string('year_month', 6);
            // The Meeting this row's hours belong to, or the sentinel 0 for "no meeting"
            // (see the class note). Non-null so the uniqueness grain below actually holds.
            $table->unsignedBigInteger('meeting_id')->default(0);
            $table->unsignedInteger('scheduled_hours')->default(0);
            $table->unsignedInteger('extra_hours')->default(0);
            // Stored, not computed — the model keeps it equal to scheduled + extra so a
            // report can sum one column (ADR-0022 §1).
            $table->unsignedInteger('total_hours')->default(0);
            $table->timestamps();

            // The grain: one row per (Member, Group, month, Meeting). The sentinel-backed
            // meeting_id makes the no-meeting case a single real value, so this holds it.
            $table->unique(['member_id', 'group_id', 'year_month', 'meeting_id']);
            // The two query shapes the reports run (ADR-0022 §Model layer): a Group's
            // records across a fiscal year, and a Member's records across a fiscal year.
            $table->index(['group_id', 'year_month']);
            $table->index(['member_id', 'year_month']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hours_records');
    }
};
