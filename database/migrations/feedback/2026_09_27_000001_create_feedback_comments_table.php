<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Comments on Feedback items (#677, ADR-0029 §8), on the feedback connection. A flat
 * list under each item, never edited. The foreign key stays inside the feedback
 * database; the Tester is plain text, like on the item (§2).
 */
return new class extends Migration
{
    protected $connection = 'feedback';

    public function up(): void
    {
        Schema::create('feedback_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('feedback_item_id')->constrained()->cascadeOnDelete();
            $table->string('tester_name', 100);
            $table->text('body');
            $table->timestamps();

            // An item's comments list oldest first.
            $table->index(['feedback_item_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feedback_comments');
    }
};
