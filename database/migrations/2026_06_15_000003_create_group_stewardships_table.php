<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * `group_stewardships` records a Group declaring it operates an org-wide
     * system function the rest of the DMV depends on (`App\Enums\StewardshipFunction`).
     * A Group may steward several functions, so this is its own table rather than
     * a column on `groups`; the unique index keeps a Group from stewarding the
     * same function twice. Authority stays explicit and per-Group (ADR-0011) —
     * member-administration reach is membership in the Group stewarding
     * `member_admin`, not a standalone flag.
     */
    public function up(): void
    {
        Schema::create('group_stewardships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained()->cascadeOnDelete();
            $table->string('function');
            $table->unique(['group_id', 'function']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('group_stewardships');
    }
};
