<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The Document access log (#712, spec #290, ADR-0030): one row per download, who and
     * when. Rows are written once and never edited. No retention rule in this spec.
     *
     * The log outlives what it records (story 55, leak tracing): no foreign keys, so deleting
     * a Document or a Member leaves its rows in place. The Group and the name the Document
     * had (a file's original filename, or a link's display name) are snapshotted at log time
     * so a row still reads after the Document is gone.
     */
    public function up(): void
    {
        Schema::create('document_downloads', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('document_id')->index();
            $table->unsignedBigInteger('member_id')->index();
            $table->unsignedBigInteger('group_id');
            $table->string('original_filename');
            $table->timestamp('downloaded_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_downloads');
    }
};
