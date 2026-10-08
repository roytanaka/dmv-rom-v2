<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Document categories (#724, spec #721, ADR-0030 §4): the headings one Folder (or the
     * library root, `folder_id` null) groups its direct children under. `name` is content
     * (ADR-0004), unique within one owning Folder: the index covers Folders; root names (null
     * `folder_id`, which a unique index never compares) are held unique by the Form Requests.
     * Deleting a Folder deletes its own list (it is empty by then, so nothing points at it).
     *
     * Folders and Documents each get a nullable `category_id`. It names a Document category of
     * their parent Folder (or the root), held by the Form Requests. Deleting a Document
     * category moves its items to Other (null).
     */
    public function up(): void
    {
        Schema::create('document_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained('groups')->cascadeOnDelete();
            $table->foreignId('folder_id')->nullable()->constrained('document_folders')->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();

            $table->unique(['group_id', 'folder_id', 'name']);
        });

        Schema::table('document_folders', function (Blueprint $table) {
            $table->foreignId('category_id')->nullable()->after('parent_id')->constrained('document_categories')->nullOnDelete();
        });

        Schema::table('documents', function (Blueprint $table) {
            $table->foreignId('category_id')->nullable()->after('folder_id')->constrained('document_categories')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropConstrainedForeignId('category_id');
        });

        Schema::table('document_folders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('category_id');
        });

        Schema::dropIfExists('document_categories');
    }
};
