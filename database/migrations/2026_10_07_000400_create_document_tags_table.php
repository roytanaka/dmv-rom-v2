<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A Group's Tags and the pivot that puts them on Documents (#717, spec #290, ADR-0030 §4).
     * A Tag name is unique within its Group. Deleting a Tag (or its Document) drops the pivot
     * rows, so a deleted Tag leaves no Document carrying it.
     */
    public function up(): void
    {
        Schema::create('document_tags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained('groups')->cascadeOnDelete();
            $table->string('name', 100);
            $table->timestamps();

            $table->unique(['group_id', 'name']);
        });

        Schema::create('document_tag', function (Blueprint $table) {
            $table->foreignId('document_id')->constrained('documents')->cascadeOnDelete();
            $table->foreignId('document_tag_id')->constrained('document_tags')->cascadeOnDelete();

            $table->primary(['document_id', 'document_tag_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_tag');
        Schema::dropIfExists('document_tags');
    }
};
