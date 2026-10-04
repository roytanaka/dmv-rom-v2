<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Whether a Group collects the tour-leading second box (#447, PRD #443, ADR-0023 §2, §5) —
     * the extra-interaction split beside its visitor count. A **third** Group setting, separate
     * from `collects_visitor_count`: Visitor Guides collects a count and not the split, which is
     * exactly the case one switch cannot express, so the split gets its own.
     *
     * Named `collects_*` for the same reason as `collects_visitor_count`: it switches a **field**
     * on inside the existing Scheduling section — a second box on the sign-out panel — not a whole
     * section with a tab behind it. It implies `collects_visitor_count` (the extra box sits beside
     * the count, and a tour's visitor number is the tour itself); that is seeded and asserted
     * rather than enforced as a database constraint, matching the other capability flags.
     *
     * Defaults false: only a tour-leading Group is turned on. A desk or gallery Group leaves it
     * off — its visitor count already is an interaction count, so a second box would ask the same
     * question twice.
     */
    public function up(): void
    {
        Schema::table('groups', function (Blueprint $table) {
            $table->boolean('collects_extra_interactions')->default(false)->after('collects_visitor_count');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('groups', function (Blueprint $table) {
            $table->dropColumn('collects_extra_interactions');
        });
    }
};
