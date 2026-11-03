<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Renewal settings (#937, ADR-0038 §9, §11): the one row member administration edits once a year
 * to run the Renewal. It replaces the Legacy app's one-row control table. The Sustaining attachment
 * is a private file kept like a Document (docs/conventions.md § Documents): an opaque disk path, the
 * user-facing filename and type apart. The external addresses, the attachment and `updated_by` are
 * v2-only (ADR-0038 §13).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('renewal_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('show_renewal')->default(false);
            $table->unsignedSmallInteger('renewal_year')->nullable();
            $table->date('active_send_date')->nullable();
            $table->date('sustaining_send_date')->nullable();
            $table->text('active_message_1')->nullable();
            $table->text('active_message_2')->nullable();
            $table->text('sustaining_message_1')->nullable();
            $table->text('sustaining_message_2')->nullable();
            $table->string('sustaining_attachment_path')->nullable();
            $table->string('sustaining_attachment_filename', 200)->nullable();
            $table->string('sustaining_attachment_mime_type')->nullable();
            $table->json('external_addresses');
            $table->foreignId('updated_by')->nullable()->constrained('members')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('renewal_settings');
    }
};
