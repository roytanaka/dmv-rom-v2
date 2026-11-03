<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The Sign-in log (#926, ADR-0038 §12). The IP address and user agent are nullable: a year blanks
 * them, and the legacy rows import as Member and time only. The user agent is text because the
 * header has no length limit, and an over-long one must not fail a sign-in.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('sign_ins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->timestamp('signed_in_at')->index();
            $table->string('ip', 45)->nullable();
            $table->text('user_agent')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sign_ins');
    }
};
