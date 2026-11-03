<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The Category history (#920, ADR-0038 §4): one dated row per change of a Member's Category. The
 * newest row is the Member's Category and status date. `category` is a Category value or the
 * `reinstate` marker Reinstate writes before Active, as the Legacy app did. The departure reason
 * rides on the row so removing an entry recomputes it too.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('category_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained('members')->cascadeOnDelete();
            $table->string('category', 32);
            $table->date('date');
            $table->string('departure_reason', 32)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('members')->nullOnDelete();
            $table->timestamps();

            $table->index(['member_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('category_changes');
    }
};
