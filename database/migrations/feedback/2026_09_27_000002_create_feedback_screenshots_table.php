<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Screenshots on Feedback items (#678, ADR-0029 §9), on the feedback connection. The file
 * sits on the private disk under a UUID name; the row keeps the Tester's original
 * filename for the download (docs/conventions.md § Documents). The foreign key stays
 * inside the feedback database (§2).
 */
return new class extends Migration
{
    protected $connection = 'feedback';

    public function up(): void
    {
        Schema::create('feedback_screenshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('feedback_item_id')->constrained()->cascadeOnDelete();
            $table->string('original_filename', 200);
            $table->string('storage_path');
            $table->string('mime_type', 100);
            $table->unsignedInteger('size_bytes');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feedback_screenshots');
    }
};
