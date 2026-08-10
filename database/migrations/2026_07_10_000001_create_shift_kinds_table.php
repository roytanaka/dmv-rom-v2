<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * ShiftKinds — the small per-Group vocabulary of kinds of shift (ADR-0021 §3),
     * ADR-0015's "Catalog" renamed and scoped. Each row belongs to one Group; a Shift's
     * `shift_kind_id` points here (nullable — Reception's Shifts carry null, because
     * legacy hardcodes the string "Desk" in PHP).
     *
     * A real table rather than a free string because even the Group whose kind *looks*
     * like free text reads its vocabulary from a table, and a string cannot carry a
     * qualification requirement later — that requirement will hang off the kind, never
     * off the Shift. The first pass builds none of the credential machinery; the slot
     * is decided and stays empty.
     *
     * `name` is officer-authored content: single-column, as-authored, never translated
     * (ADR-0004). Rows are **seeded** — the maintenance CRUD screen is deferred
     * (ADR-0021 §3), so there is no `created_by` and no soft-delete.
     */
    public function up(): void
    {
        Schema::create('shift_kinds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained('groups')->cascadeOnDelete();
            $table->string('name');
            // Retire a kind without deleting history: an inactive kind is no longer
            // offered to new Shifts but still labels the ones that already carry it.
            $table->boolean('active')->default(true);
            // The Group's own display order for its kinds — the vocabulary is small and
            // hand-curated, so ordering is authored, not derived.
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shift_kinds');
    }
};
