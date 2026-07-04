<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add the nullable `logo_key` column (PRD #253): a Group's identity mark on
     * the launcher tiles. Unlike `banner_key` there is NO backfill — a logo
     * belongs to one specific Group, not to a Kind, so null is the common,
     * expected state and resolves to a single generic fallback mark at render.
     * Logos are assigned by hand (in the demo seeder for staging; by the legacy
     * import for production), never derived.
     */
    public function up(): void
    {
        Schema::table('groups', function (Blueprint $table) {
            $table->string('logo_key')->nullable()->after('banner_key');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('groups', function (Blueprint $table) {
            $table->dropColumn('logo_key');
        });
    }
};
