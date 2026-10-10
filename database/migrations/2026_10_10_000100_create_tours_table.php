<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tours — a Group's own list of concrete tours (#788, ADR-0033 §1). Docents and Guides du
     * ROM each keep one. A shift kind is only the slot label; the Tour is what a Docent gives.
     *
     * `name` is officer-authored content: single-column, as-authored, never translated
     * (ADR-0004), unique within the Group. `active` retires a Tour without deleting history.
     * `open_to_all` marks a Tour any Member may give without a qualification (ADR-0033 §6).
     */
    public function up(): void
    {
        Schema::create('tours', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained('groups')->cascadeOnDelete();
            $table->string('name');
            $table->boolean('active')->default(true);
            $table->boolean('open_to_all')->default(false);
            // The Group's own display order for its Tours — authored, not derived.
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['group_id', 'name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tours');
    }
};
