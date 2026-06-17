<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Replace the single `name` column with `first_name` + `last_name`. The
     * Directory sorts and jumps by surname and the legacy migration source already
     * separates the two names, so the split is the foundation every member-facing
     * slice reads (#168, ADR-0017 §6). No middle, preferred, or display name.
     */
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->string('first_name')->after('id');
            $table->string('last_name')->after('first_name');
        });

        Schema::table('members', function (Blueprint $table) {
            $table->dropColumn('name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->string('name')->after('id');
        });

        Schema::table('members', function (Blueprint $table) {
            $table->dropColumn(['first_name', 'last_name']);
        });
    }
};
