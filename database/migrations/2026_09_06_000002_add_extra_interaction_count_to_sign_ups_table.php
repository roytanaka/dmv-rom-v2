<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The tour-leading second box lands on the Sign-up (#447, PRD #443, ADR-0023 §2), beside
     * the visitor count #445 added. `extra_interaction_count` is how many visitors this Member
     * served **outside the tour they led** — the split Docents and GDR have kept for years, into
     * a column no legacy report has ever read.
     *
     * **Stored apart, never folded.** The two numbers sit in separate columns and are added only
     * where a report asks for total interactions; nothing combines them at entry. It is a second
     * nullable unsigned integer with no default: **null means nobody recorded a value; zero means
     * someone recorded zero.** Unlike the visitor count it is optional at the entry surface — a
     * tour with no extra interactions is a real zero the volunteer may leave blank.
     *
     * Tour-leading Groups only: a desk or gallery Group's visitor count already is an interaction
     * count, so its Sign-ups leave this null. The switch is a Group-level setting of its own,
     * separate from the one that turns the visitor count on.
     */
    public function up(): void
    {
        Schema::table('sign_ups', function (Blueprint $table) {
            $table->unsignedInteger('extra_interaction_count')->nullable()->after('visitor_count');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sign_ups', function (Blueprint $table) {
            $table->dropColumn('extra_interaction_count');
        });
    }
};
