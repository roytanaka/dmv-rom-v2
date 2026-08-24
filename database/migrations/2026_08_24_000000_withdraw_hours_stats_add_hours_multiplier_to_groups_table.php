<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The Groups-schema half of ADR-0022 (hours and statistics). Two related moves,
     * amending ADR-0010's capability set:
     *
     * - §3: the `has_hours_stats` capability is withdrawn. Hours joins roster as an
     *   always-on feature — every Group and sub-Group carries the Hours menu — so an
     *   on/off flag that would be true everywhere has no reason to exist. The set
     *   goes from seven capabilities to six.
     * - §7: an `hours_multiplier` moves legacy's hardcoded walks-to-hours conversion
     *   (`walker.php`'s `2*$total`) out of code and into data. Default 1; ROMWalks 2.
     *   A future Group with its own ratio becomes a data edit, not a deploy.
     */
    public function up(): void
    {
        Schema::table('groups', function (Blueprint $table) {
            // Whole-number ratio applied when scheduled hours are recalculated
            // (ADR-0022 §7). 1 for every Group but the walking tours; never zero.
            $table->unsignedInteger('hours_multiplier')->default(1)->after('has_announcements');
        });

        Schema::table('groups', function (Blueprint $table) {
            $table->dropColumn('has_hours_stats');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('groups', function (Blueprint $table) {
            $table->boolean('has_hours_stats')->default(false)->after('has_vetting');
        });

        Schema::table('groups', function (Blueprint $table) {
            $table->dropColumn('hours_multiplier');
        });
    }
};
