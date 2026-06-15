<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The `group_member` pivot is the single authoritative source of "who is in
     * this Group, with what standing" — the table every authorization decision
     * and roster reads. `status` is the within-Group standing (App\Enums\
     * MembershipStatus), distinct from the Member's DMV-wide `category`. The LOA
     * window answers "on leave from this Group until X" before the scheduling
     * feature exists.
     */
    public function up(): void
    {
        Schema::create('group_member', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('full')->index();
            // Per-Group leave window; both nullable (no leave scheduled).
            $table->date('loa_start')->nullable();
            $table->date('loa_end')->nullable();
            // At most one membership per (Group, Member).
            $table->unique(['group_id', 'member_id']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('group_member');
    }
};
