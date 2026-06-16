<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add the `has_announcements` capability flag (ADR-0010 delta): the switch
     * that lets a Group post to the org-wide news feed. Mirrors the existing
     * capability flags — a per-Group on/off toggle, default off.
     */
    public function up(): void
    {
        Schema::table('groups', function (Blueprint $table) {
            $table->boolean('has_announcements')->default(false)->after('has_hours_stats');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('groups', function (Blueprint $table) {
            $table->dropColumn('has_announcements');
        });
    }
};
