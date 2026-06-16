<?php

use App\Enums\Kind;
use App\Enums\LifecycleState;
use App\Enums\Scope;
use App\Models\Group;
use Illuminate\Database\QueryException;

/*
 * Model-test pattern for the spine (PRD #126, slice 2 / #128).
 *
 * Asserts external behaviour and invariants through the Group model's public
 * API — tree round-trips, enum + flag casts, illegal-value rejection, and the
 * active-Groups query helper (ADR-0010) — not column existence.
 */

it('round-trips a parent and its children', function () {
    $root = Group::factory()->create(['name' => 'DMV']);
    $committee = Group::factory()->create(['parent_id' => $root->id]);
    $program = Group::factory()->create(['parent_id' => $root->id]);

    expect($committee->fresh()->parent->is($root))->toBeTrue()
        ->and($root->fresh()->children->pluck('id'))
        ->toContain($committee->id, $program->id);
});

it('leaves a root Group with a null parent', function () {
    $root = Group::factory()->create();

    expect($root->parent_id)->toBeNull()
        ->and($root->fresh()->parent)->toBeNull();
});

it('casts kind, scope and lifecycle_state to their enums', function () {
    $group = Group::factory()->create([
        'kind' => Kind::Program,
        'scope' => Scope::Program,
        'lifecycle_state' => LifecycleState::Archived,
    ]);

    $fresh = $group->fresh();

    expect($fresh->kind)->toBe(Kind::Program)
        ->and($fresh->scope)->toBe(Scope::Program)
        ->and($fresh->lifecycle_state)->toBe(LifecycleState::Archived);
});

it('casts the capability flags and time_boxed to booleans', function () {
    $group = Group::factory()->create([
        'has_meetings' => true,
        'has_documents' => true,
        'has_scheduling' => true,
        'has_content_catalog' => true,
        'has_vetting' => true,
        'has_hours_stats' => true,
        'has_announcements' => true,
        'time_boxed' => true,
    ]);

    $fresh = $group->fresh();

    expect($fresh->has_meetings)->toBeTrue()
        ->and($fresh->has_documents)->toBeTrue()
        ->and($fresh->has_scheduling)->toBeTrue()
        ->and($fresh->has_content_catalog)->toBeTrue()
        ->and($fresh->has_vetting)->toBeTrue()
        ->and($fresh->has_hours_stats)->toBeTrue()
        ->and($fresh->has_announcements)->toBeTrue()
        ->and($fresh->time_boxed)->toBeTrue();
});

it('rejects an illegal kind value', function () {
    Group::factory()->create(['kind' => 'federation']);
})->throws(ValueError::class);

it('enforces a unique slug', function () {
    Group::factory()->create(['slug' => 'dmv-executive']);
    Group::factory()->create(['slug' => 'dmv-executive']);
})->throws(QueryException::class);

it('refuses to delete a Group that still has children', function () {
    $root = Group::factory()->create();
    Group::factory()->create(['parent_id' => $root->id]);

    $root->delete();
})->throws(QueryException::class);

it('builds factory states for each Kind', function () {
    expect(Group::factory()->standingCommittee()->create()->kind)->toBe(Kind::StandingCommittee)
        ->and(Group::factory()->program()->create()->kind)->toBe(Kind::Program)
        ->and(Group::factory()->workingGroup()->create()->kind)->toBe(Kind::WorkingGroup)
        ->and(Group::factory()->project()->create()->kind)->toBe(Kind::Project)
        ->and(Group::factory()->cohort()->create()->kind)->toBe(Kind::Cohort);
});

it('turns scheduling on for programs', function () {
    expect(Group::factory()->program()->create()->has_scheduling)->toBeTrue();
});

it('returns only active Groups from the active scope', function () {
    $active = Group::factory()->create();
    $archived = Group::factory()->archived()->create();
    $liveCohort = Group::factory()->timeBoxed()->create([
        'end_date' => now()->addMonth(),
    ]);
    $expiredCohort = Group::factory()->timeBoxed()->create([
        'end_date' => now()->subDay(),
    ]);

    $ids = Group::active()->pluck('id');

    expect($ids)->toContain($active->id, $liveCohort->id)
        ->and($ids)->not->toContain($archived->id, $expiredCohort->id);
});

it('treats a time-boxed Group with no end date as still active', function () {
    $openEnded = Group::factory()->timeBoxed()->create(['end_date' => null]);

    expect(Group::active()->pluck('id'))->toContain($openEnded->id);
});
