<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A Group's How to join text (#867, ADR-0037 §3): the join steps the Secretary or Chair
     * writes for the Overview's How to join card. As-authored content in one language, never
     * translated, like `description`. Null shows the card's fallback line.
     */
    public function up(): void
    {
        Schema::table('groups', function (Blueprint $table) {
            $table->text('how_to_join')->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('groups', function (Blueprint $table) {
            $table->dropColumn('how_to_join');
        });
    }
};
