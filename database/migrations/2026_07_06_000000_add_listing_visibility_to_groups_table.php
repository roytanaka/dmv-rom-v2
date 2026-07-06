<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add the `listing_visibility` facet (PRD #268, ADR-0019): who a Group is
     * listed to in navigation. A stored, first-class axis alongside Kind / Scope
     * / Lifecycle — never derived from tree position.
     *
     * Defaults to `group` (fail-closed on exposure): a new subgroup is listed
     * only to its parent's members until someone deliberately makes it `public`.
     * Indexed because the navigation prune filters on it.
     */
    public function up(): void
    {
        Schema::table('groups', function (Blueprint $table) {
            $table->string('listing_visibility')->default('group')->index()->after('scope');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('groups', function (Blueprint $table) {
            $table->dropColumn('listing_visibility');
        });
    }
};
