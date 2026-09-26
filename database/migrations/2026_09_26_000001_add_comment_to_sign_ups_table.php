<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The volunteer's optional comment on their post-shift entry (#655, PRD #651): visitor questions,
 * how the shift went, or issues from the floor. At most 2,000 characters, enforced by the Form
 * Request. Nullable: existing rows have none. Only the author and a schedule admin read it; no
 * report does.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sign_ups', function (Blueprint $table) {
            $table->text('comment')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('sign_ups', function (Blueprint $table) {
            $table->dropColumn('comment');
        });
    }
};
