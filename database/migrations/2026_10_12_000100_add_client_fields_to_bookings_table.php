<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The client half of a Booking and its one Shift (#795, ADR-0032 §1). The Shift holds the
     * staffing (times, the group-tour kind, docents needed as capacity); deleting it deletes the
     * Booking. The Tour given restricts on delete, like the booking type: a Tour a Booking names is
     * retired, not deleted. Client, leader, order number and comments are content, never translated
     * (ADR-0004). `earned_correction` is the Statistician's figure (§7, #797), null while the
     * worked-out figure stands; money is decimal(10,2), like the booking-type rates.
     *
     * Bookings so far exist only in tests and the demo seed (#794 added the bare table), so the
     * new required columns carry no backfill.
     */
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->foreignId('shift_id')->after('group_id')->unique()->constrained('shifts')->cascadeOnDelete();
            $table->foreignId('tour_id')->after('shift_id')->constrained('tours')->restrictOnDelete();
            $table->string('client')->after('booking_type_id');
            $table->unsignedInteger('visitors')->after('client');
            $table->string('leader')->nullable()->after('visitors');
            $table->string('order_number')->nullable()->after('leader');
            $table->date('order_date')->nullable()->after('order_number');
            $table->text('comments')->nullable()->after('order_date');
            $table->decimal('earned_correction', 10, 2)->nullable()->after('comments');
            $table->index(['group_id', 'client']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropIndex(['group_id', 'client']);
            $table->dropConstrainedForeignId('shift_id');
            $table->dropConstrainedForeignId('tour_id');
            $table->dropColumn(['client', 'visitors', 'leader', 'order_number', 'order_date', 'comments', 'earned_correction']);
        });
    }
};
