<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The bookings capability (#794, ADR-0032 §2): the switch that gives a Group Bookings, the
     * Booker role and the booking-type card. Docents and GDR turn it on. Beside it, the two
     * group-tour settings (§1, §4): the shift kind a Booking's Shift takes, and the label the
     * month's group-tour Schedule is named from ("Group tours", "Visites de groupe"). The label
     * is officer-authored content, never translated (ADR-0004). Deleting the kind clears the
     * setting.
     */
    public function up(): void
    {
        Schema::table('groups', function (Blueprint $table) {
            $table->boolean('has_bookings')->default(false)->after('has_announcements');
            $table->foreignId('group_tour_shift_kind_id')->nullable()->after('loa_removes_qualifications')
                ->constrained('shift_kinds')->nullOnDelete();
            $table->string('group_tour_label')->nullable()->after('group_tour_shift_kind_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('groups', function (Blueprint $table) {
            $table->dropConstrainedForeignId('group_tour_shift_kind_id');
            $table->dropColumn(['has_bookings', 'group_tour_label']);
        });
    }
};
