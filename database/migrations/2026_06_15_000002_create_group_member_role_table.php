<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Roles ride on a membership as their own rows (`App\Enums\Role`), not boolean
     * columns — so adding or scoping a role later is a data change, not a
     * migration. One membership may carry several roles (e.g. a Chair who also
     * schedules); the unique index keeps a single membership from holding the same
     * role twice. The capability-gating invariant (a capability-backed role only
     * attaches to a Group whose flag is on) is enforced at the model layer.
     */
    public function up(): void
    {
        Schema::create('group_member_role', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_member_id')->constrained('group_member')->cascadeOnDelete();
            $table->string('role');
            $table->unique(['group_member_id', 'role']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('group_member_role');
    }
};
