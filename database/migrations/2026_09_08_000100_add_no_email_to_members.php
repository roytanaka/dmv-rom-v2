<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The no-email flag (#483, ADR-0024 §9). One Records-set switch that silences every
 * mail to a Member — Broadcasts, Direct messages, Reminders, Notices. Checked once,
 * when Delivery rows are written: a flagged Member gets no row. It replaces legacy's
 * opt-out by editing the address to a sentinel; migrating that sentinel onto this
 * column belongs to the legacy migration plan.
 *
 * Not mass-assignable (kept off Member::$fillable), flipped only through the
 * dedicated, member-administration-gated action — the same shape as `super_tier`.
 * Indexed like the other sparse admin flags: a handful of Members carry it, and the
 * Audience resolver excludes them at send time.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->boolean('no_email')->default(false)->index()->after('support_operator');
        });
    }

    public function down(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->dropColumn('no_email');
        });
    }
};
