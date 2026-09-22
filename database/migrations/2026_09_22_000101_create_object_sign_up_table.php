<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The pivot between Sign-ups and Objects (#584, ADR-0026 §3) — a Sign-up carries zero or
     * more Objects, and an Object is reserved by zero or more Sign-ups. Object reservation lives
     * on the Sign-up, not the Shift, because several volunteers may share one Shift and each
     * reserves their own (ADR-0015's object-reservation strategy).
     *
     * Created here so the schema is complete; it stays unused until the seats ticket puts Objects
     * on Sign-ups. Unique on the pair, and both foreign keys cascade on delete so dropping either
     * side clears its links.
     */
    public function up(): void
    {
        Schema::create('object_sign_up', function (Blueprint $table) {
            $table->id();
            $table->foreignId('object_id')->constrained('objects')->cascadeOnDelete();
            $table->foreignId('sign_up_id')->constrained('sign_ups')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['object_id', 'sign_up_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('object_sign_up');
    }
};
