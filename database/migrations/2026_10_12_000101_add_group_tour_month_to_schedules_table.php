<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Marks a month's group-tour Schedule (#795, ADR-0032 §4): the first day of the month whose
     * Bookings it holds, null on every other Schedule. The app finds the month's Schedule by this
     * key, never by its name, which is officer-editable. One per Group and month.
     */
    public function up(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            $table->date('group_tour_month')->nullable()->after('description');
            $table->unique(['group_id', 'group_tour_month']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            $table->dropUnique(['group_id', 'group_tour_month']);
            $table->dropColumn('group_tour_month');
        });
    }
};
