<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Shifts — a slot on a Schedule (ADR-0021 §2). A Shift is a **slot, not a seat**:
     * `capacity` is an integer, never N byte-identical rows. That is the decision the
     * whole model turns on — legacy's seat-rows are why raising capacity there destroys
     * every existing sign-up. Raising capacity is now one `UPDATE`, and per-seat data
     * lives on the Sign-up (which lands in a later slice).
     *
     * `schedule_id` is **mandatory**: there are no orphan Shifts. Schedules may overlap
     * in time, so a Shift inherits its whole context — audience, visibility, past-ness —
     * from its Schedule, and a genuinely one-off Shift is a Schedule with a one-day
     * range, not a nullable FK that branches every read two ways.
     *
     * `shift_kind_id` is **nullable** — Reception's Shifts carry null. Duration is always
     * derived from `starts_at` / `ends_at`, so there is no duration column.
     *
     * Deliberately **absent** (ADR-0021 §2): `Count` in either legacy sense, `location`,
     * a state column (a Shift has no state — it inherits everything from its Schedule),
     * a free-text note, a duration column, and any uniqueness constraint on identical
     * Shifts (two identical Shifts in one Schedule are permitted).
     */
    public function up(): void
    {
        Schema::create('shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('schedule_id')->constrained('schedules')->cascadeOnDelete();
            // Instants, stored in UTC and read on the org wall clock (OrgTime). "Has this
            // passed?" and "do these overlap?" are each one comparison over the pair.
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            // A slot's seat count, not a row count. Full is when the Sign-up count reaches
            // it; lowering it below the current count is blocked (both land with #357).
            $table->unsignedInteger('capacity')->default(1);
            // Nullable: Reception's Shifts carry null. Requirements will hang off the kind.
            $table->foreignId('shift_kind_id')->nullable()->constrained('shift_kinds')->nullOnDelete();
            // `group` (default) — the owning Group's Members; `open` — any Member who can
            // see the Schedule. The permissive case is always a deliberate choice.
            $table->string('audience')->default('group');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shifts');
    }
};
