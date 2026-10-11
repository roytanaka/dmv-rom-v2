<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The Group's booking copy address (#799, ADR-0032 §9): an optional address every Booking mail
     * is copied to. The old app hard-codes a ROM staff address; here the Group sets its own on the
     * Group tours card. It is not a Member, so its copy is a Delivery with no Member (see the
     * companion migration).
     */
    public function up(): void
    {
        Schema::table('groups', function (Blueprint $table) {
            $table->string('booking_copy_email')->nullable()->after('group_tour_label');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('groups', function (Blueprint $table) {
            $table->dropColumn('booking_copy_email');
        });
    }
};
