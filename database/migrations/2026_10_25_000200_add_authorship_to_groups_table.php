<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Who created a Group and who last changed its structure (#842, ADR-0036 §8). Set by
     * DMV Settings writes; seeded and imported Groups stay null. A removed Member leaves
     * the Group standing with the reference cleared.
     */
    public function up(): void
    {
        Schema::table('groups', function (Blueprint $table) {
            $table->foreignId('created_by')->nullable()->constrained('members')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('members')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('groups', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by');
            $table->dropConstrainedForeignId('updated_by');
        });
    }
};
