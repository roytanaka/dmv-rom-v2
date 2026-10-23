<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Booking types — a Group's billing classes for its Bookings (#794, ADR-0032 §6): Tour Paid,
     * Tour Free, Tour Internal, Spot Paid, Spot Free. Each carries a rate per visitor and a rate
     * per docent-hour, from which Earned is worked out (§7).
     *
     * `name` is officer-authored content: single-column, as-authored, never translated
     * (ADR-0004), unique within the Group. `active` retires a type a Booking still uses.
     */
    public function up(): void
    {
        Schema::create('booking_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained('groups')->cascadeOnDelete();
            $table->string('name');
            $table->decimal('rate_per_visitor', 8, 2)->unsigned()->default(0);
            $table->decimal('rate_per_docent_hour', 8, 2)->unsigned()->default(0);
            $table->boolean('active')->default(true);
            // The Group's own display order for its types — authored, not derived.
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['group_id', 'name']);
            $table->index(['group_id', 'active', 'sort_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('booking_types');
    }
};
