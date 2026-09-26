<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Who last saved a seat's post-shift numbers, and when (#654, PRD #651). Stamped by the server on
 * every save — the volunteer's own and an officer's correction — so the Post-shift report can show
 * "Last edited by". Both nullable: existing rows stay unstamped. Deleting the editor's Member row
 * clears the stamp rather than the seat.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sign_ups', function (Blueprint $table) {
            $table->foreignId('last_edited_by_id')->nullable()->constrained('members')->nullOnDelete();
            $table->timestamp('last_edited_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('sign_ups', function (Blueprint $table) {
            $table->dropConstrainedForeignId('last_edited_by_id');
            $table->dropColumn('last_edited_at');
        });
    }
};
