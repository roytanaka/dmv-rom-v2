<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Exhibition revenue (#800, ADR-0032 §12): a monthly money figure a Group's Statistician enters
     * from the Tour Summary, added to its grand total. One row per Group and month; `year_month` is
     * the `YYYYMM` bucket, as on `hours_records`. Money as on the booking-type rates: decimal, two
     * places.
     */
    public function up(): void
    {
        Schema::create('exhibition_revenues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained('groups')->cascadeOnDelete();
            $table->string('year_month', 6);
            $table->decimal('amount', 10, 2)->unsigned()->default(0);
            $table->timestamps();

            $table->unique(['group_id', 'year_month']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exhibition_revenues');
    }
};
