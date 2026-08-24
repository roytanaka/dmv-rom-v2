<?php

use App\Models\Group;
use App\Models\HoursRecord;
use App\Models\Member;
use App\Support\GroupHoursMatrix;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
 * The fiscal-year matrix query object (#411, PRD #406, ADR-0022 §5) — the feature's one
 * new seam. Given a Group and a fiscal year it returns the Group's twelve monthly buckets,
 * the same twelve including every descendant however deep, and the year-to-date total for
 * both. This is the "service class with non-trivial logic" the conventions reserve a unit
 * test for, so it is exercised directly here — rows built by factory, no HTTP.
 *
 * The unit seam needs a database (the rollup is a query and the tree a relation), so this
 * file opts into the migrated, transaction-wrapped TestCase rather than the bare unit base.
 */
uses(TestCase::class, RefreshDatabase::class);

/** A record at the no-meeting grain carrying the given scheduled and extra hours. */
function matrixRecord(Group $group, string $yearMonth, int $scheduled = 0, int $extra = 0): HoursRecord
{
    return HoursRecord::factory()->create([
        'member_id' => Member::factory()->create()->id,
        'group_id' => $group->id,
        'year_month' => $yearMonth,
        'meeting_id' => HoursRecord::NO_MEETING,
        'scheduled_hours' => $scheduled,
        'extra_hours' => $extra,
        'total_hours' => $scheduled + $extra,
    ]);
}

it('labels twelve buckets in April-to-March order for the fiscal year', function () {
    $matrix = GroupHoursMatrix::for(Group::factory()->create(), 2026);

    expect($matrix->months)->toHaveCount(12)
        ->and($matrix->months[0])->toBe('202504')
        ->and($matrix->months[11])->toBe('202603');
});

it('sums the Group\'s own hours into the right month bucket', function () {
    $group = Group::factory()->create();
    matrixRecord($group, '202504', scheduled: 2, extra: 1); // April → bucket 0
    matrixRecord($group, '202603', scheduled: 3, extra: 4); // March → bucket 11

    $matrix = GroupHoursMatrix::for($group, 2026);

    expect($matrix->own[0])->toBe(3)
        ->and($matrix->own[11])->toBe(7)
        // A month with nothing on file reads zero, not a gap.
        ->and($matrix->own[5])->toBe(0);
});

it('returns twelve zeros for a Group with no records, never a short list or a null', function () {
    $matrix = GroupHoursMatrix::for(Group::factory()->create(), 2026);

    expect($matrix->own)->toBe(array_fill(0, 12, 0))
        ->and($matrix->subtree)->toBe(array_fill(0, 12, 0))
        ->and($matrix->ownYtd)->toBe(0)
        ->and($matrix->subtreeYtd)->toBe(0);
});

it('rolls a grandchild three levels down into the subtree total but not the own total', function () {
    $group = Group::factory()->create();
    $child = Group::factory()->create(['parent_id' => $group->id]);
    $grandchild = Group::factory()->create(['parent_id' => $child->id]);

    matrixRecord($group, '202504', extra: 1);
    matrixRecord($child, '202504', extra: 2);
    matrixRecord($grandchild, '202504', extra: 4);

    $matrix = GroupHoursMatrix::for($group, 2026);

    // Own is the Group's rows alone; subtree reaches the grandchild.
    expect($matrix->own[0])->toBe(1)
        ->and($matrix->subtree[0])->toBe(7);
});

it('files a March record in the fiscal year ending that March and an April record in the next', function () {
    $group = Group::factory()->create();
    matrixRecord($group, '202603', extra: 3); // last month of Fiscal 2026
    matrixRecord($group, '202604', extra: 4); // first month of Fiscal 2027

    expect(GroupHoursMatrix::for($group, 2026)->ownYtd)->toBe(3)
        ->and(GroupHoursMatrix::for($group, 2027)->ownYtd)->toBe(4);
});

it('makes year-to-date the sum of the twelve buckets, for both own and subtree', function () {
    $group = Group::factory()->create();
    $child = Group::factory()->create(['parent_id' => $group->id]);
    matrixRecord($group, '202504', extra: 2);
    matrixRecord($group, '202510', extra: 5);
    matrixRecord($group, '202603', extra: 3);
    matrixRecord($child, '202504', extra: 10);

    $matrix = GroupHoursMatrix::for($group, 2026);

    expect($matrix->ownYtd)->toBe(array_sum($matrix->own))
        ->and($matrix->ownYtd)->toBe(10)
        ->and($matrix->subtreeYtd)->toBe(array_sum($matrix->subtree))
        ->and($matrix->subtreeYtd)->toBe(20);
});

it('does not re-apply the Group\'s hours multiplier — it is already baked into the stored hours', function () {
    // ROMWalks stores its walks already doubled (ADR-0022 §7): the multiplier is applied
    // once at recalculation, so the matrix must reflect the stored numbers as-is and never
    // multiply a second time.
    $walker = Group::factory()->create(['hours_multiplier' => 2]);
    matrixRecord($walker, '202504', scheduled: 10); // 5 raw hours, already ×2 on store

    $matrix = GroupHoursMatrix::for($walker, 2026);

    expect($matrix->own[0])->toBe(10)
        ->and($matrix->subtree[0])->toBe(10);
});
