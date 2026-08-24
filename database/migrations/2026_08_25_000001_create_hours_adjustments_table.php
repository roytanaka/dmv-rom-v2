<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The Hours adjustment log (ADR-0022 §6) — append-only provenance for the monthly
     * bucket. Legacy's additive update overwrites `extra_hours` in place and leaves no
     * author and no history: one `lastUpdate` timestamp per row, so a mistyped entry is
     * unattributable. Every extra-hours write therefore also appends one row here — the
     * signed `delta` applied and the authoring Member — in the same transaction, so a
     * record can never exist without its trail.
     *
     * There is no update path: rows are only ever inserted (the history is append-only,
     * not overwritten alongside the total). `delta` is a signed integer — a negative
     * correction is a first-class entry. `created_by` is always the authenticated Member;
     * the write seam never trusts a member id from the request.
     */
    public function up(): void
    {
        Schema::create('hours_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hours_record_id')->constrained('hours_records')->cascadeOnDelete();
            // Signed — a negative correction is recorded as a negative delta. The change
            // actually applied to extra_hours (after the zero floor), so the deltas sum to
            // the current extra_hours total.
            $table->integer('delta');
            $table->foreignId('created_by')->constrained('members')->cascadeOnDelete();
            $table->timestamps();

            $table->index('hours_record_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hours_adjustments');
    }
};
