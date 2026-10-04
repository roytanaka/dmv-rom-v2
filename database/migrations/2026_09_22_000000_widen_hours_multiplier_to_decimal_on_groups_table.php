<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * ADR-0026 §7 (amending ADR-0022 §7): the walks-to-hours ratio stops being a
     * whole number. Gallery Interpreters credit one hour per 45-minute unit — a
     * multiplier of 1.333 — so `hours_multiplier` widens to an unsigned decimal
     * with three places. A plain column change: there is no production data to
     * convert, and the whole-number values already stored round-trip unchanged.
     */
    public function up(): void
    {
        Schema::table('groups', function (Blueprint $table) {
            $table->decimal('hours_multiplier', 6, 3)->unsigned()->default(1)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('groups', function (Blueprint $table) {
            $table->unsignedInteger('hours_multiplier')->default(1)->change();
        });
    }
};
