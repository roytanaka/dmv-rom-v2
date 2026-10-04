<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Whether a Group collects GDR's five visitor origins (#448, PRD #443, ADR-0023 §3) — the
     * provenance split beside its visitor count. A **fourth** Group setting, one Group wide: GDR
     * alone is turned on, and a second Group asking is when provenance gets generalised.
     *
     * Named `collects_*` for the same reason as `collects_visitor_count`: it switches **fields**
     * on inside the existing Scheduling section — five origin boxes on the sign-out panel — not a
     * whole section with a tab behind it. It implies `collects_visitor_count` (the five sum to
     * the count, which is the count itself); that is seeded and asserted rather than enforced as a
     * database constraint, matching the other capability flags.
     *
     * Defaults false: only GDR is turned on. Every other Group leaves it off and its Sign-ups
     * refuse a provenance value rather than folding it anywhere.
     */
    public function up(): void
    {
        Schema::table('groups', function (Blueprint $table) {
            $table->boolean('collects_visitor_provenance')->default(false)->after('collects_extra_interactions');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('groups', function (Blueprint $table) {
            $table->dropColumn('collects_visitor_provenance');
        });
    }
};
