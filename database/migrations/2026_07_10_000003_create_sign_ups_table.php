<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sign-ups — one Member on one Shift (#357, PRD #352, ADR-0021 §Sign-up). A Sign-up
     * is a single row: a Member either takes a Shift themselves or is placed on it by a
     * Scheduler — one entity, two actors — and legacy writes the identical row for both.
     *
     * A **unique constraint on (`shift_id`, `member_id`)** enforces one seat per Member per
     * Shift: taking a seat someone else needs by holding two on the same Shift is
     * impossible at the storage layer. Two Sign-ups on *overlapping* Shifts stay allowed —
     * the required `ends_at` makes overlap computable and the first pass deliberately does
     * not compute it.
     *
     * Deliberately **absent**: any provenance / authorship column (who created the
     * Sign-up). That would make `sign_ups` the one table in the app with an authorship
     * column (point 2 of #334); the Member–Scheduler distinction is not recorded here.
     * Per-seat data (a volunteer's own hours, their own visitor count) will hang here when
     * the hours work lands — the slot is why capacity is an integer, not N seat-rows.
     */
    public function up(): void
    {
        Schema::create('sign_ups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shift_id')->constrained('shifts')->cascadeOnDelete();
            $table->foreignId('member_id')->constrained('members')->cascadeOnDelete();
            $table->timestamps();

            // One seat per Member per Shift. A second Sign-up on the same Shift is rejected
            // at the storage layer; overlapping-Shift Sign-ups are untouched by this.
            $table->unique(['shift_id', 'member_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sign_ups');
    }
};
