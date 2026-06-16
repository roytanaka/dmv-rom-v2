<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Index the membership pivots on the keys the authority resolver
     * (`Member::canActAs`, ADR-0017) reads. The resolver loads one Member's
     * memberships+roles up front, so the hot lookups are "this Member's
     * memberships" and "this membership's roles".
     *
     * `group_member.member_id` had no usable index of its own: the existing
     * unique is `(group_id, member_id)`, whose leading column is `group_id`, so
     * it can't serve a `member_id`-keyed lookup. This adds it.
     *
     * `group_member_role.group_member_id` is already the leading column of that
     * table's `(group_member_id, role)` unique, which serves the roles lookup —
     * a standalone index would be redundant, so none is added there.
     */
    public function up(): void
    {
        Schema::table('group_member', function (Blueprint $table) {
            $table->index('member_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('group_member', function (Blueprint $table) {
            $table->dropIndex(['member_id']);
        });
    }
};
