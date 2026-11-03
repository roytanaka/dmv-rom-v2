<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An Invitation (#902, ADR-0034): an occasion that belongs to its Host, not to a
 * Group. The stored `state` is Draft or Open; Closed is computed from `date`.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('host_id')->constrained('members')->restrictOnDelete();
            $table->string('name');
            $table->date('date')->index();
            $table->time('start_time');
            $table->time('end_time');
            $table->text('description');
            $table->string('state')->default('draft')->index();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invitations');
    }
};
