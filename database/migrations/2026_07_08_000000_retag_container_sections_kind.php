<?php

use App\Enums\Kind;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Retag the three org-level container sections from `standing_committee` to
     * the new `Kind::Container` marker (PRD #289, #292): they group other Groups
     * but are not themselves destinations — no page, no roster, no capabilities.
     *
     * `special-projects` is left as `standing_committee` — completing its
     * promotion to a real coordinating Group with a page and a roster.
     *
     * A one-off data fix for existing/staging rows; a fresh `migrate --seed`
     * already seeds these as `Kind::Container` directly.
     */
    public function up(): void
    {
        DB::table('groups')
            ->whereIn('slug', ['governance-operations', 'programs', 'friends'])
            ->update(['kind' => Kind::Container->value]);
    }

    /**
     * Reverse the retag, restoring the container sections to standing committees.
     */
    public function down(): void
    {
        DB::table('groups')
            ->whereIn('slug', ['governance-operations', 'programs', 'friends'])
            ->update(['kind' => Kind::StandingCommittee->value]);
    }
};
