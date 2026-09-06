<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The per-seat visitor count lands on the Sign-up (#445, PRD #443, ADR-0023 §2) — the
     * slot the create migration reserved. `visitor_count` is how many visitors this Member
     * served on this Shift, one nullable unsigned integer per seat.
     *
     * **Null means nobody has recorded a value; zero means someone recorded zero visitors.**
     * Both are real states and nothing may collapse the two — the whole feature is bought by
     * that distinction holding, so the column is nullable with no default (never zero-filled).
     *
     * The tour-leading second box (`extra_interaction_count`) and GDR's five provenance
     * integers are switched on for their own Groups and land with their own slices; this
     * migration adds only the count every collecting Group uses.
     */
    public function up(): void
    {
        Schema::table('sign_ups', function (Blueprint $table) {
            $table->unsignedInteger('visitor_count')->nullable()->after('member_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sign_ups', function (Blueprint $table) {
            $table->dropColumn('visitor_count');
        });
    }
};
