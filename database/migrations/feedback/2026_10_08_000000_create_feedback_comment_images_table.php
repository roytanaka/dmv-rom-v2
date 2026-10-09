<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Images on Feedback comments (#778, spec #773, ADR-0029 §8, §9), on the feedback
 * connection. The same shape as an item's screenshots: the file sits on the private disk
 * under a UUID name, and the row keeps the Tester's original filename for the download
 * (docs/conventions.md § Documents). A comment may now carry only images, so its body
 * becomes nullable.
 */
return new class extends Migration
{
    protected $connection = 'feedback';

    public function up(): void
    {
        Schema::create('feedback_comment_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('feedback_comment_id')->constrained()->cascadeOnDelete();
            $table->string('original_filename', 200);
            $table->string('storage_path');
            $table->string('mime_type', 100);
            $table->unsignedInteger('size_bytes');
            $table->timestamps();
        });

        Schema::table('feedback_comments', function (Blueprint $table) {
            $table->text('body')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feedback_comment_images');
    }
};
