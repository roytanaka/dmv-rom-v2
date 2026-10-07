<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * ADR-0030 §1: the content catalog merges into the Document library, so the
     * `has_content_catalog` capability is withdrawn. `has_documents` is the one
     * capability left for a Group's files. Nothing is in production and staging
     * reseeds, so no data moves.
     */
    public function up(): void
    {
        Schema::table('groups', function (Blueprint $table) {
            $table->dropColumn('has_content_catalog');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('groups', function (Blueprint $table) {
            $table->boolean('has_content_catalog')->default(false)->after('has_scheduling');
        });
    }
};
