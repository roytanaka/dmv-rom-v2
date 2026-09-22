<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Objects — a Group's handling collection, the artefacts a Gallery Interpreter takes onto
     * the floor (#584, ADR-0026 §3). The same three columns and the same maintenance shape as
     * `shift_kinds`: each row belongs to one Group, carries an `active` flag so a retired Object
     * drops out of the pickers without losing the Sign-ups already holding it, and a `sort_order`
     * for the Group's own display order.
     *
     * `name` is officer-authored content: single-column, as-authored, never translated
     * (ADR-0004). Unique within the Group — a duplicate name in the same Group is rejected, the
     * same name in another Group is free. Table name stays the glossary term `objects`; only the
     * model class is prefixed, because PHP reserves `Object`.
     */
    public function up(): void
    {
        Schema::create('objects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained('groups')->cascadeOnDelete();
            $table->string('name');
            // Retire an Object without deleting history: an inactive Object is no longer offered
            // on new Sign-ups but still names the ones that already reserve it.
            $table->boolean('active')->default(true);
            // The Group's own display order for its Objects — the collection is hand-curated, so
            // ordering is authored, not derived.
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
        Schema::dropIfExists('objects');
    }
};
