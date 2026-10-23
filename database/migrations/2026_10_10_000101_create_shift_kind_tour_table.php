<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The pivot between shift kinds and Tours (#788, ADR-0033 §1) — a kind maps to zero or more
     * Tours, a Tour to zero or more kinds. A kind with no Tours behaves as before. Replaces the
     * old app's slot-to-tour mapping table. Both keys cascade, so dropping either side clears
     * its links.
     */
    public function up(): void
    {
        Schema::create('shift_kind_tour', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shift_kind_id')->constrained('shift_kinds')->cascadeOnDelete();
            $table->foreignId('tour_id')->constrained('tours')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['shift_kind_id', 'tour_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shift_kind_tour');
    }
};
