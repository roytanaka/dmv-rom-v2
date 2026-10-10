<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A Delivery may go to an address that is no Member (#799, ADR-0032 §9): a Group's copy
     * address, which gets its own copy of every Booking mail. Such a row has no Member, so
     * `member_id` becomes nullable; the Drain renders it in the locale its payload names. Member
     * rows are unchanged. Each NULL is distinct in the (kind, shift_id, member_id) unique index,
     * so copies never collide.
     */
    public function up(): void
    {
        Schema::table('deliveries', function (Blueprint $table) {
            $table->foreignId('member_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations. Copy-address rows have no Member to keep them, so they go first.
     */
    public function down(): void
    {
        DB::table('deliveries')->whereNull('member_id')->delete();

        Schema::table('deliveries', function (Blueprint $table) {
            $table->foreignId('member_id')->nullable(false)->change();
        });
    }
};
