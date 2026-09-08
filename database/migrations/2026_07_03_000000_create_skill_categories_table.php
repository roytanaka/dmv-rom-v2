<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * `skill_categories` is the org-owned grouping the Skills catalog renders under
     * (PRD #243): a fixed, seeded vocabulary of category headings. `code` is the
     * stable idempotency key the seeder heals on; `display_order` fixes the heading
     * order the settings page shows; `active` retires a whole category without
     * deleting rows, so a Member's historical `member_skill` selections survive.
     */
    public function up(): void
    {
        Schema::create('skill_categories', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->unsignedInteger('display_order')->default(0)->index();
            $table->boolean('active')->default(true)->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('skill_categories');
    }
};
