<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Schedules — the scheduling container ADR-0021 §1 supplies. Each row belongs to
     * exactly one Group (which runs scheduling); a Schedule is a name, a date range,
     * a state, and an optional description, and holds nothing yet (Shifts land in a
     * later slice). A `draft` is admin-only; a `published` one follows the Group's
     * listing visibility (SchedulePolicy).
     *
     * `name` / `description` are officer-authored content: single-column, as-authored,
     * never translated (ADR-0004). Past-ness derives from `ends_on`, so there is no
     * `archived` / `final` column; there is deliberately no `YYYYMM`, no monthly
     * marker, no `visible` flag separate from `state`, and no `created_by` (ADR-0021 §1).
     */
    public function up(): void
    {
        Schema::create('schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained('groups')->cascadeOnDelete();
            $table->string('name');
            $table->date('starts_on');
            $table->date('ends_on')->index();
            // Two states, one audience each: `draft` (admin-only) and `published`
            // (the Group's listing-visibility audience). Not a separate `visible` flag.
            $table->string('state')->default('draft');
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('schedules');
    }
};
