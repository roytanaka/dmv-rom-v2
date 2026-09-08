<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Meetings — a Group's first own-data capability (#190, PRD #186). Each row
     * belongs to the Group that runs it; the list is members-only (MeetingPolicy)
     * even though the Overview and Roster are org-open.
     *
     * `title` / `description` / `location` are member-authored content: single-
     * column, as-authored, never translated (ADR-0004). `video_url` is a plain
     * external link (legacy Zoom integration is dropped to a link). `is_published`
     * is the drafting flag — ordinary members never see a hidden meeting; officer
     * drafting affordances land with the meetings-CRUD slice (#193).
     */
    public function up(): void
    {
        Schema::create('meetings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained('groups')->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->dateTime('held_at')->index();
            $table->string('location')->nullable();
            $table->string('video_url')->nullable();
            // Drafting flag: a hidden meeting is invisible to ordinary members until
            // published. Defaults to published so a plainly-created meeting is visible.
            $table->boolean('is_published')->default(true)->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('meetings');
    }
};
