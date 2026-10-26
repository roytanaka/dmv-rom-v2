<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * ADR-0036 §6: the Scope axis (organization / program / subteam) is
     * withdrawn. No screen, policy, or nav code ever read it — a Group's place
     * in the tree is its `parent_id`, and its nature is its Kind — so the
     * column goes with the enum. Nothing is in production and staging reseeds,
     * so no data moves.
     */
    public function up(): void
    {
        Schema::table('groups', function (Blueprint $table) {
            $table->dropIndex(['scope']);
        });

        Schema::table('groups', function (Blueprint $table) {
            $table->dropColumn('scope');
        });
    }

    /**
     * Reverse the migrations. The column was non-null; it comes back nullable
     * because no value can be derived for the existing rows.
     */
    public function down(): void
    {
        Schema::table('groups', function (Blueprint $table) {
            $table->string('scope')->nullable()->index()->after('kind');
        });
    }
};
