<?php

use App\Models\Member;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Split operator/support access out of the super-tier grant (authority-model
 * refinement). `super_tier` is org authority — the President / VPs run the DMV.
 * Impersonating a volunteer for support is a *maintainer* power, different in kind:
 * least-privilege, out-of-band, held by the engineer, not by an executive. It gets
 * its own explicit marker here so the two principals stop sharing one flag.
 *
 * Deliberately NOT routed through a Gate: `Gate::before` grants super-tier every
 * ability, so a Gate-backed operator check would hand it straight back to the
 * President. The marker is read directly ({@see Member::isSupportOperator()}).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->boolean('support_operator')->default(false)->index()->after('super_tier');
        });
    }

    public function down(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->dropColumn('support_operator');
        });
    }
};
