<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * `member_skill` is the plain pivot recording which catalog skills a Member is
     * willing to use for DMV/ROM (PRD #243). Deliberately bare — a composite
     * (member_id, skill_id) primary key and nothing else: selection is a flat
     * multi-select with no proficiency/level, so the pivot carries no extra state
     * (contrast `group_member`, whose explicit-pivot model carries status/roles).
     * Both sides cascade on delete so a removed Member or retired-and-deleted skill
     * doesn't leave dangling selections.
     */
    public function up(): void
    {
        Schema::create('member_skill', function (Blueprint $table) {
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->foreignId('skill_id')->constrained()->cascadeOnDelete();
            $table->primary(['member_id', 'skill_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('member_skill');
    }
};
