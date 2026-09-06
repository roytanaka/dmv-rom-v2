<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Extra interactions on the Hours record (ADR-0023 §6, amending ADR-0022 §1) — visitors a
     * Member served outside any Shift, entered beside extra hours on the Hours tab. Six Groups
     * have no scheduling route into the department's headline visitor number, and one of them —
     * ROM Travel — runs no scheduling at all, so a hand-typed count is their whole presence in it.
     *
     * A whole integer at the same grain as the rest of the record (Member, Group, month), but
     * **outside `total_hours`** — a visitor count is not time worked and must never enter an
     * hours figure. The model's `recomputeTotal()` deliberately leaves it out.
     *
     * The `hours_adjustments` log gains a `field` discriminator in the same pass so an interactions
     * write is attributable and correctable independently of an hours write. Legacy's extra-interactions
     * entry only ever adds, refuses negatives, and has no second writer anywhere — so a Member who types
     * 2280 for 228 can never correct it, and neither can a Statistician. Recording which field each
     * signed delta applied to is what makes the correction path exist (ADR-0023 §6).
     */
    public function up(): void
    {
        Schema::table('hours_records', function (Blueprint $table) {
            // Outside total_hours — the model keeps total = scheduled + extra hours and never
            // folds this in. Default 0 so an existing row reads zero, not null.
            $table->unsignedInteger('extra_interactions')->default(0)->after('total_hours');
        });

        Schema::table('hours_adjustments', function (Blueprint $table) {
            // Which part of the record this signed delta moved — 'extra_hours' or
            // 'extra_interactions'. Defaults to 'extra_hours' so the existing hours-only log
            // keeps its meaning; every new write states its field explicitly.
            $table->string('field', 32)->default('extra_hours')->after('hours_record_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('hours_records', function (Blueprint $table) {
            $table->dropColumn('extra_interactions');
        });

        Schema::table('hours_adjustments', function (Blueprint $table) {
            $table->dropColumn('field');
        });
    }
};
