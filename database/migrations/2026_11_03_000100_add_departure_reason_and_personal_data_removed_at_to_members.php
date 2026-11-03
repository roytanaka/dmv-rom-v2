<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Why a Resigned or Withdrawn Member left, and when Remove personal data reached a
     * Member (#916, ADR-0038 §4, §6). The second is what places a Member in the Past list.
     */
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->string('departure_reason')->nullable();
            $table->timestamp('personal_data_removed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->dropColumn(['departure_reason', 'personal_data_removed_at']);
        });
    }
};
