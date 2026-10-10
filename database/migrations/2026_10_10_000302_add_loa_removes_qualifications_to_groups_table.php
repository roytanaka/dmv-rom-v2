<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The Group's LOA rule (#793, ADR-0033 §7): whether going on LOA makes every qualification
     * inactive. Off by default (Docents keep theirs); GDR turns it on.
     */
    public function up(): void
    {
        Schema::table('groups', function (Blueprint $table) {
            $table->boolean('loa_removes_qualifications')->default(false)->after('trainee_tour_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('groups', function (Blueprint $table) {
            $table->dropColumn('loa_removes_qualifications');
        });
    }
};
