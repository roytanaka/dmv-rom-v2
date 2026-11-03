<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The two structured lists on the Member record (#918, ADR-0038 §3). A language code is en,
     * fr, asl, lsq, or the name of any other language as the Records office typed it. Years is
     * text, as the Legacy app keeps it: a range, a single year, or blank.
     */
    public function up(): void
    {
        Schema::create('member_languages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->string('language_code', 64);
            $table->boolean('speaks')->default(false);
            $table->boolean('reads')->default(false);
            $table->boolean('writes')->default(false);
            $table->timestamps();

            $table->unique(['member_id', 'language_code']);
        });

        Schema::create('board_positions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->string('position');
            $table->string('years')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('members')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('members')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('board_positions');
        Schema::dropIfExists('member_languages');
    }
};
