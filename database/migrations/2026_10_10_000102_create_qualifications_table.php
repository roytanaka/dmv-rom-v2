<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Qualifications — a Member holding a Tour in a Group (ADR-0033 §3). Keyed on the
     * Membership, so removing a Membership deletes its qualifications. A Tour cannot be deleted
     * while a qualification points at it (restrict); retire it instead. An inactive
     * qualification is kept but counts for nothing. The Last vet date is shown, never enforced.
     */
    public function up(): void
    {
        Schema::create('qualifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_member_id')->constrained('group_member')->cascadeOnDelete();
            $table->foreignId('tour_id')->constrained('tours')->restrictOnDelete();
            $table->boolean('active')->default(true);
            $table->date('last_vet_date')->nullable();
            $table->timestamps();

            $table->unique(['group_member_id', 'tour_id']);
            // A Tour's active holders — the Tours page and the by-Tour screen.
            $table->index(['tour_id', 'active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('qualifications');
    }
};
