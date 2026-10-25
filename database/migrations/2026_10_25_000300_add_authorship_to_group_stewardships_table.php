<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Authorship on a stewardship row (#846, ADR-0036 §8): who created it and who last wrote
     * it, so the Stewardships page can say "last changed by X on date". A column convention,
     * not an audit log. Nullable (seeded rows have no author) and nulled when the Member row
     * goes, so a departed author never blocks a delete.
     */
    public function up(): void
    {
        Schema::table('group_stewardships', function (Blueprint $table) {
            $table->foreignId('created_by')->nullable()->constrained('members')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('members')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('group_stewardships', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by');
            $table->dropConstrainedForeignId('updated_by');
        });
    }
};
