<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A Group's Documents (#712, spec #290, ADR-0003, ADR-0030). Each belongs to one Group.
     * `folder_id` null means the library root. It has no foreign key yet: the Folders table
     * lands with the Folders ticket (#714), which adds the constraint.
     *
     * A file Document keeps its bytes on the private `local` disk under an opaque UUID
     * (`storage_path`); `original_filename` is the safe name a download hands back
     * (docs/conventions.md § Documents). A link Document has `url` and no file columns.
     * `title` and `description` are single-column content (ADR-0004).
     */
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained('groups')->cascadeOnDelete();
            $table->unsignedBigInteger('folder_id')->nullable()->index();
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->string('kind', 16);
            $table->string('url', 2048)->nullable();
            $table->string('original_filename', 200)->nullable();
            $table->string('storage_path')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->foreignId('uploaded_by_id')->nullable()->constrained('members')->nullOnDelete();
            $table->timestamp('uploaded_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
