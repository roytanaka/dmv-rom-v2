<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * GDR's five visitor origins land on the Sign-up (#448, PRD #443, ADR-0023 §3) — the slice
     * the visitor-count migration reserved. `visitors_france_europe`, `visitors_quebec`,
     * `visitors_toronto`, `visitors_rest_of_canada` and `visitors_other_countries` are how many
     * of this seat's visitors came from each origin, five nullable unsigned integers beside the
     * count. GDR has filled them on every counted tour since 2020.
     *
     * **The five must sum to `visitor_count`** — a server rule ({@see RecordSignUpVisitorsRequest}),
     * so `visitor_count` holds derived data for GDR. It stays: it is the column every other Group
     * uses and every report reads (ADR-0023 §3, precedent ADR-0022's `total_hours`). Null means
     * nobody recorded a value; zero means someone recorded zero — the same distinction the count
     * keeps.
     *
     * **One Group in fifteen years is not a pattern** (ADR-0023 §3): five columns on the shared
     * `sign_ups` is the whole design — no breakdown table, no model, no relation, no JSON column.
     * They are accepted as visible GDR-only noise on a shared table; a second Group asking is when
     * it gets generalised. Switched on per Group by `collects_visitor_provenance`, exactly as the
     * count is by `collects_visitor_count`.
     */
    public function up(): void
    {
        Schema::table('sign_ups', function (Blueprint $table) {
            $table->unsignedInteger('visitors_france_europe')->nullable()->after('extra_interaction_count');
            $table->unsignedInteger('visitors_quebec')->nullable()->after('visitors_france_europe');
            $table->unsignedInteger('visitors_toronto')->nullable()->after('visitors_quebec');
            $table->unsignedInteger('visitors_rest_of_canada')->nullable()->after('visitors_toronto');
            $table->unsignedInteger('visitors_other_countries')->nullable()->after('visitors_rest_of_canada');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sign_ups', function (Blueprint $table) {
            $table->dropColumn([
                'visitors_france_europe',
                'visitors_quebec',
                'visitors_toronto',
                'visitors_rest_of_canada',
                'visitors_other_countries',
            ]);
        });
    }
};
