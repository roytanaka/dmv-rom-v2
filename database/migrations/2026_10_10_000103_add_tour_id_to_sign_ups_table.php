<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The Tour a Sign-up records (ADR-0033 §2). Nullable: a Shift whose kind maps to no Tour
     * carries none. A Tour cannot be deleted while a Sign-up names it (restrict).
     */
    public function up(): void
    {
        Schema::table('sign_ups', function (Blueprint $table) {
            $table->foreignId('tour_id')->nullable()->after('member_id')->constrained('tours')->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sign_ups', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tour_id');
        });
    }
};
