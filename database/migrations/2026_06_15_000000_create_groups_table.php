<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The `groups` table is the spine's organizing entity (ADR-0010): one row
     * per committee, program, working group, project, or cohort. Kind / Scope /
     * Lifecycle are stored explicitly; the six capability flags switch features
     * on per Group (roster is always-on, so it has no flag).
     */
    public function up(): void
    {
        Schema::create('groups', function (Blueprint $table) {
            $table->id();
            // One tree, the organization at the root (root = null). Parentage
            // carries structure only, never authority (ADR-0011) — and a Group
            // with children can't be deleted out from under them.
            $table->foreignId('parent_id')->nullable()
                ->constrained('groups')->restrictOnDelete();
            $table->string('slug')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            // The three orthogonal axes (App\Enums\Kind / Scope / LifecycleState),
            // stored explicitly — never derived from depth or capability flags.
            $table->string('kind')->index();
            $table->string('scope')->index();
            $table->string('lifecycle_state')->default('active')->index();
            // Lifecycle window: a time-boxed Group (a cohort/project) reaches its
            // end_date and should be archived; the active-Groups query excludes
            // expired ones before that flip happens.
            $table->boolean('time_boxed')->default(false)->index();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable()->index();
            $table->unsignedInteger('display_order')->default(0)->index();
            // Capability flags — the small fixed set from ADR-0010. Each
            // capability's data lives in its own FK-linked tables; the Group row
            // carries only the on/off switch.
            $table->boolean('has_meetings')->default(false);
            $table->boolean('has_documents')->default(false);
            $table->boolean('has_scheduling')->default(false);
            $table->boolean('has_content_catalog')->default(false);
            $table->boolean('has_vetting')->default(false);
            $table->boolean('has_hours_stats')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('groups');
    }
};
