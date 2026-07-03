<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * `skills` is the org-owned catalog a Member selects from (PRD #243): one row
     * per skill the organization recognizes, grouped under a `skill_categories`
     * heading. Flat — no description, no proficiency/level. `code` is the stable
     * idempotency key the seeder heals on; `active` retires a single skill without
     * deleting rows, preserving `member_skill` history. A category with children
     * can't be deleted out from under them (`restrictOnDelete`).
     */
    public function up(): void
    {
        Schema::create('skills', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->foreignId('category_id')->constrained('skill_categories')->restrictOnDelete();
            $table->string('name');
            $table->boolean('active')->default(true)->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('skills');
    }
};
