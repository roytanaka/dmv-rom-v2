<?php

use App\Models\DocumentFolder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A Group's Document library Folders (#714, spec #290, ADR-0030 §3). A tree per Group:
     * `parent_id` null means a top-level Folder. Depth is capped in code
     * ({@see DocumentFolder::MAX_DEPTH}), not here. `name` is content (ADR-0004),
     * unique among siblings: the index covers subfolders; top-level names (null parent, which
     * a unique index never compares) are held unique by the Form Requests. `visibility` is
     * stored on top-level Folders only; {@see DocumentFolder} keeps that invariant on save.
     *
     * Also adds the `documents.folder_id` foreign key #712 left out. Both Folder references
     * restrict deletes: the app refuses to delete a Folder that still holds Folders or
     * Documents, and the database backs that up.
     */
    public function up(): void
    {
        Schema::create('document_folders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained('groups')->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('document_folders')->restrictOnDelete();
            $table->string('name');
            // Who reads it (#715, ADR-0030 §5): `group` or `members` on a top-level Folder,
            // null on a subfolder, which inherits its top-level Folder's setting.
            $table->string('visibility', 16)->nullable();
            $table->timestamps();

            $table->unique(['group_id', 'parent_id', 'name']);
        });

        Schema::table('documents', function (Blueprint $table) {
            $table->foreign('folder_id')->references('id')->on('document_folders')->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropForeign(['folder_id']);
        });

        Schema::dropIfExists('document_folders');
    }
};
