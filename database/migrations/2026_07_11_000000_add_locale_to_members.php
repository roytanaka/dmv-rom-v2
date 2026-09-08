<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The Member's preferred locale — the language system-generated messages address them
     * in (#358, PRD #352). The first reader is the Sign-up cancellation email, which renders
     * per recipient (a Mailable implementing HasLocalePreference); post-login redirects and
     * future notification URLs read the same column (docs/conventions.md § i18n). Two-letter
     * code, English by default, so an existing Member is addressed in English until they
     * choose otherwise.
     */
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->string('locale', 5)->default('en')->after('category');
        });
    }

    public function down(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->dropColumn('locale');
        });
    }
};
