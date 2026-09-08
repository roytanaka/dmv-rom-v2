<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The per-kind watch flag (#487, ADR-0024 §7). Whether the empty-desk alert watches Shifts of
 * this kind: an unstaffed Shift is named only when its kind carries this flag, so a Group
 * without kinds cannot run the alert at all, as in legacy. Off by default; a Scheduler or Chair
 * marks which kinds to watch through the empty-desk settings endpoint. The seeder turns it on
 * for Visitor Guides' Desk kind.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shift_kinds', function (Blueprint $table) {
            $table->boolean('alert_when_empty')->default(false)->after('active');
        });
    }

    public function down(): void
    {
        Schema::table('shift_kinds', function (Blueprint $table) {
            $table->dropColumn('alert_when_empty');
        });
    }
};
