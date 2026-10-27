<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Followers of Feedback items (#857, spec #856, ADR-0029 §15), on the feedback connection.
 * A Follower is an email address, not a Member (§2): no link into the main database. The
 * locale is the one the Tester used when they followed, so their emails render in it.
 * One Follower per item per address; the address is stored trimmed and lowercased.
 */
return new class extends Migration
{
    protected $connection = 'feedback';

    public function up(): void
    {
        Schema::create('feedback_followers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('feedback_item_id')->constrained()->cascadeOnDelete();
            $table->string('email', 254);
            $table->string('locale', 8);
            $table->timestamps();

            $table->unique(['feedback_item_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feedback_followers');
    }
};
