<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Self-service contact record (#232, PRD #228). Extends the single `phone`
     * (now the Member's *primary* phone) with two optional secondary numbers and a
     * structured home address, all Member-editable from Settings → Profile.
     *
     * Visibility tiers (ADR-0017 §field-level visibility) are enforced downstream
     * in MemberResource, not here: the three phones are peer-gated behind
     * `viewContact`; the home address is Records-only (`viewAddress`) and never
     * reaches a peer-visible payload.
     *
     * Province defaults to Ontario and country to Canada — the ROM's home province,
     * so the overwhelming-majority case needs no input. Columns are nullable: the
     * whole contact record is optional.
     */
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->string('alternate_phone')->nullable()->after('phone');
            $table->string('business_phone')->nullable()->after('alternate_phone');
            $table->string('address_street')->nullable()->after('business_phone');
            $table->string('address_city')->nullable()->after('address_street');
            $table->string('address_province')->nullable()->default('ON')->after('address_city');
            $table->string('address_postal_code')->nullable()->after('address_province');
            $table->string('address_country')->nullable()->default('Canada')->after('address_postal_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->dropColumn([
                'alternate_phone',
                'business_phone',
                'address_street',
                'address_city',
                'address_province',
                'address_postal_code',
                'address_country',
            ]);
        });
    }
};
