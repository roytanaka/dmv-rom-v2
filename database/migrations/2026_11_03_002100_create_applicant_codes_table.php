<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The Applicant code (#930, ADR-0038 §8): one settings row the Applicant Chair sets. While it is
 * published and not expired, the sign-in page offers the public application form.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('applicant_codes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64)->nullable();
            $table->boolean('published')->default(false);
            $table->date('expires_on')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('members')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('applicant_codes');
    }
};
