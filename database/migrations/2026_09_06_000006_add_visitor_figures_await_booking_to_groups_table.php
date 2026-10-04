<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Whether a Group's visitor figures are knowingly incomplete because a booking audience it
     * depends on is not counted yet (#451, PRD #443, ADR-0023 §6). Some Groups draw part of their
     * visitor number from a group-booking table the booking map has not delivered — ROMForYou most
     * of all, which reads 293 instead of 1,055 in fiscal 2026 under the composition rule. Under-
     * reporting with no visible cause reads as a bug; this flag lets Summary Visitor Interactions
     * mark the row and say why, so the gap is honest and puts visible pressure on the booking work.
     *
     * A Group setting rather than a derived value: whether a Group has an outstanding booking
     * source is a fact about the Group, not something the visitor data can reveal — the missing
     * rows are missing. Defaults false: an ordinary Group's figures are complete as they stand.
     */
    public function up(): void
    {
        Schema::table('groups', function (Blueprint $table) {
            $table->boolean('visitor_figures_await_booking')->default(false)->after('collects_visitor_provenance');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('groups', function (Blueprint $table) {
            $table->dropColumn('visitor_figures_await_booking');
        });
    }
};
