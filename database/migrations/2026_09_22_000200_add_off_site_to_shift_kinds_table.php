<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The off-site flag on a shift kind (#587, ADR-0026 §4). An Object on a Sign-up whose Shift
 * carries an off-site kind is held from the start of the day before to the end of the day after,
 * so an Object packed for a CNE event cannot also go onto a gallery floor the day either side.
 * Off by default; a Scheduler or Chair marks the event stations and _Off site_ through the
 * shift-kind maintenance block. Legacy computed the same window per event; the flag on the kind
 * makes it automatic, at the cost of one boolean column.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shift_kinds', function (Blueprint $table) {
            $table->boolean('off_site')->default(false)->after('alert_when_empty');
        });
    }

    public function down(): void
    {
        Schema::table('shift_kinds', function (Blueprint $table) {
            $table->dropColumn('off_site');
        });
    }
};
