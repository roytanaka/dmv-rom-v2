<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Adds the two member fields the centralized MemberResource allowlist needs to
     * be meaningful (#154, ADR-0017 §field-level visibility):
     *
     * - `phone`: contact PII, gated behind the `viewContact` ability alongside the
     *   existing `email` — private by default, exposed only to authorized viewers.
     * - `photo_path`: the always-public profile photo. Storage path, nullable until
     *   the photo-upload feature lands; the resource exposes it as `photo` ("if
     *   uploaded"). Null today, so the directory degrades to name-only.
     */
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->string('phone')->nullable()->after('email');
            $table->string('photo_path')->nullable()->after('phone');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->dropColumn(['phone', 'photo_path']);
        });
    }
};
