<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The sent record for mail that has no Shift or Invitation to key on (#942, ADR-0038 §9): a
     * `series` name (`active-1`, `sustaining-2`, `rom-expiry`) and a `series_year`, the period the
     * series serves. A Renewal reminder holds the renewal year (2027); the ROM-expiry notice (#943)
     * holds year and month as YYYYMM (202606), hence an int, not a smallint. The unique index makes
     * "one per series per period per Member" a database fact; other kinds carry nulls, and each
     * NULL is distinct in a unique index, so they never collide.
     */
    public function up(): void
    {
        Schema::table('deliveries', function (Blueprint $table) {
            $table->string('series')->nullable()->after('invitation_id');
            $table->unsignedInteger('series_year')->nullable()->after('series');
            $table->unique(['kind', 'series', 'series_year', 'member_id']);
        });
    }

    public function down(): void
    {
        Schema::table('deliveries', function (Blueprint $table) {
            $table->dropUnique(['kind', 'series', 'series_year', 'member_id']);
            $table->dropColumn(['series', 'series_year']);
        });
    }
};
