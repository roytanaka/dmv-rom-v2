<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A meeting's labelled external links (#190, PRD #186) — the conventional set
     * of agenda / minutes / report URLs. `kind` selects the (translated) label;
     * `url` is content. Deleting a meeting cascades to its links.
     *
     * External URLs for now; when the Documents capability lands they upgrade to
     * access-controlled documents.
     */
    public function up(): void
    {
        Schema::create('meeting_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meeting_id')->constrained('meetings')->cascadeOnDelete();
            $table->string('kind');
            $table->string('url');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('meeting_links');
    }
};
