<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The Invitation a Reminder is keyed to (#909, ADR-0034 §12). The unique index makes "one
     * Reminder per Invitation per Member" a database fact, as (kind, shift_id, member_id) does
     * for a Shift. Other kinds carry a null `invitation_id`, and each NULL is distinct in a
     * unique index, so they never collide.
     */
    public function up(): void
    {
        Schema::table('deliveries', function (Blueprint $table) {
            $table->foreignId('invitation_id')->nullable()->after('shift_id')->constrained('invitations')->cascadeOnDelete();
            $table->unique(['kind', 'invitation_id', 'member_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('deliveries', function (Blueprint $table) {
            $table->dropUnique(['kind', 'invitation_id', 'member_id']);
            $table->dropConstrainedForeignId('invitation_id');
        });
    }
};
