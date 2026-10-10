<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The Group's trainee Tour (#793, ADR-0033 §7): the one Tour a new Trainee gets ("Museum
     * Highlights – New Docents"). Optional. Deleting the Tour clears the setting.
     */
    public function up(): void
    {
        Schema::table('groups', function (Blueprint $table) {
            $table->foreignId('trainee_tour_id')->nullable()->after('self_serve_unit_minutes')
                ->constrained('tours')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('groups', function (Blueprint $table) {
            $table->dropConstrainedForeignId('trainee_tour_id');
        });
    }
};
