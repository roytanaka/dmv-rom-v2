<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The org-wide news feed (ADR-0010 announcements delta, ADR-0017 §5). Unlike
     * every other capability — whose data is read group-scoped — `announcements`
     * writes to this single feed that everyone reads. Each item carries the
     * `posting_group_id` of the Group that published it, so attribution survives
     * even though the read is org-wide.
     *
     * `title` / `body` are volunteer-authored content: single-column, as-authored,
     * never translated (ADR-0004).
     */
    public function up(): void
    {
        Schema::create('news', function (Blueprint $table) {
            $table->id();
            // Attribution: which Group posted this item. Indexed for the eventual
            // per-Group filtered view; the default feed read is org-wide.
            $table->foreignId('posting_group_id')->constrained('groups')->cascadeOnDelete();
            $table->string('title');
            $table->text('body');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('news');
    }
};
