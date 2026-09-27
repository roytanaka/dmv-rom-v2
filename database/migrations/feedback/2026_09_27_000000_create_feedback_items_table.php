<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Feedback items (#676, ADR-0029), on the feedback connection that deploys never reset.
 * Run with `migrate --database=feedback --path=database/migrations/feedback`; the default
 * `migrate` never sees this folder.
 *
 * No foreign keys or ids into the main database (ADR-0029 §2): staging reseeds give Members
 * new ids, so the Tester, the Member, and the impersonator are stored as plain text.
 */
return new class extends Migration
{
    protected $connection = 'feedback';

    public function up(): void
    {
        Schema::create('feedback_items', function (Blueprint $table) {
            $table->id();
            $table->string('tester_name', 100);
            $table->string('type', 32);
            $table->string('status', 32)->default('new');
            $table->text('message');
            // Client context: what only the browser knows.
            $table->string('page_url', 2048)->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->unsignedInteger('viewport_width')->nullable();
            $table->unsignedInteger('viewport_height')->nullable();
            // Server context: filled on store, never from the request body.
            $table->string('route_name')->nullable();
            $table->string('locale', 8);
            $table->string('member_name');
            $table->string('member_email');
            $table->string('impersonator_name')->nullable();
            $table->string('app_version')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feedback_items');
    }
};
