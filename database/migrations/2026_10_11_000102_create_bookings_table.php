<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bookings (ADR-0032 §1) — a client's dated group tour. This first cut (#794) holds only the
     * Group and the booking type, so a type a Booking uses cannot be deleted (the foreign key
     * restricts it too). The client half and the one Shift land with adding a Booking (#795).
     */
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained('groups')->cascadeOnDelete();
            $table->foreignId('booking_type_id')->constrained('booking_types')->restrictOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
