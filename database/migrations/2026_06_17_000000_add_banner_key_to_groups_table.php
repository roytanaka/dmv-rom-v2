<?php

use App\Enums\GroupBanner;
use App\Enums\Kind;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add the nullable `banner_key` column (#191, PRD #186): the curated banner an
     * officer selects for the Group's header. Nullable because null is a valid
     * state — the header falls back to a neutral default. Existing rows are
     * backfilled with a sensible default by Kind so the app reads with intent from
     * the first deploy; new rows may stay null until an officer chooses.
     */
    public function up(): void
    {
        Schema::table('groups', function (Blueprint $table) {
            $table->string('banner_key')->nullable()->after('description');
        });

        foreach (Kind::cases() as $kind) {
            DB::table('groups')
                ->where('kind', $kind->value)
                ->update(['banner_key' => GroupBanner::defaultFor($kind)->value]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('groups', function (Blueprint $table) {
            $table->dropColumn('banner_key');
        });
    }
};
