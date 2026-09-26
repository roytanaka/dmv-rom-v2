<?php

use App\Enums\Role;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\GroupMemberRole;
use App\Models\Meeting;
use App\Models\Member;
use App\Models\Schedule;
use App\Models\Shift;
use App\Rules\OnMinuteGrid;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Validator;

/*
 * The 5-minute grid (#639, ADR-0028). Nobody schedules to the minute, so every Shift and
 * Meeting time sits on a 5-minute step: the pickers offer only those steps, and the server
 * rejects anything else. The guard at the bottom keeps a new Form Request from taking a time
 * without the rule.
 */

/** A member holding the given role in the given Group. */
function gridOfficerOf(Group $group, Role $role): Member
{
    $member = Member::factory()->create();
    $membership = GroupMember::factory()->create(['group_id' => $group->id, 'member_id' => $member->id]);
    GroupMemberRole::factory()->create(['group_member_id' => $membership->id, 'role' => $role]);

    return $member;
}

/** A published August 2026 Schedule on a scheduling-Group. */
function gridSchedule(): Schedule
{
    return Schedule::factory()->published()->create([
        'group_id' => Group::factory()->program()->publicListing()->create()->id,
        'starts_on' => '2026-08-01',
        'ends_on' => '2026-08-31',
    ]);
}

// --- The rule ----------------------------------------------------------------

it('accepts a time on the grid and rejects one off it', function (string $value, int $step, bool $passes) {
    $validator = Validator::make(['at' => $value], ['at' => [new OnMinuteGrid($step)]]);

    expect($validator->passes())->toBe($passes);
})->with([
    'bare time on 5' => ['10:05', 5, true],
    'bare time off 5' => ['10:07', 5, false],
    'datetime-local on 5' => ['2026-08-10T10:55', 5, true],
    'stored instant off 5' => ['2026-08-10 14:03:00', 5, false],
    'seconds are off grid' => ['2026-08-10 14:05:30', 5, false],
    'quarter hour on 15' => ['2026-08-10 14:45:00', 15, true],
    'five past off 15' => ['2026-08-10 14:05:00', 15, false],
]);

it('leaves an unparseable value to the date rules', function () {
    expect(Validator::make(['at' => 'soon'], ['at' => [new OnMinuteGrid]])->passes())->toBeTrue();
});

// --- Wired into the endpoints -----------------------------------------------

it('rejects an off-grid Shift start and end', function () {
    $schedule = gridSchedule();

    $this->actingAs(gridOfficerOf($schedule->group, Role::Scheduler))
        ->post(route('shifts.store', $schedule), ['starts_at' => '2026-08-10T10:02', 'ends_at' => '2026-08-10T13:01'])
        ->assertSessionHasErrors(['starts_at', 'ends_at']);

    expect(Shift::count())->toBe(0);
});

it('rejects an off-grid time when editing a Shift', function () {
    $schedule = gridSchedule();
    $shift = Shift::factory()->create([
        'schedule_id' => $schedule->id,
        'starts_at' => '2026-08-10 14:00:00',
        'ends_at' => '2026-08-10 17:00:00',
    ]);

    $this->actingAs(gridOfficerOf($schedule->group, Role::Scheduler))
        ->patch(route('shifts.update', $shift), ['starts_at' => '2026-08-10T10:00', 'ends_at' => '2026-08-10T12:58'])
        ->assertSessionHasErrors('ends_at');
});

it('rejects an off-grid bulk Shift time', function () {
    $schedule = gridSchedule();

    $this->actingAs(gridOfficerOf($schedule->group, Role::Scheduler))
        ->post(route('shifts.bulk-store', $schedule), [
            'starts_time' => '10:01',
            'ends_time' => '13:00',
            'days_of_week' => [1],
            'from_date' => '2026-08-01',
            'to_date' => '2026-08-31',
        ])
        ->assertSessionHasErrors('starts_time');

    expect(Shift::count())->toBe(0);
});

it('rejects an off-grid Meeting time, on create and on edit', function () {
    $group = Group::factory()->workingGroup()->create();
    $secretary = gridOfficerOf($group, Role::Secretary);

    $this->actingAs($secretary)
        ->post(route('meetings.store', $group), ['title' => 'Planning', 'held_at' => '2026-07-01T14:03'])
        ->assertSessionHasErrors('held_at');

    $meeting = Meeting::factory()->create(['group_id' => $group->id]);

    $this->actingAs($secretary)
        ->patch(route('meetings.update', $meeting), ['title' => 'Planning', 'held_at' => '2026-07-01T14:59'])
        ->assertSessionHasErrors('held_at');
});

it('accepts an on-grid Meeting time', function () {
    $group = Group::factory()->workingGroup()->create();

    $this->actingAs(gridOfficerOf($group, Role::Secretary))
        ->post(route('meetings.store', $group), ['title' => 'Planning', 'held_at' => '2026-07-01T14:35'])
        ->assertSessionHasNoErrors();
});

// --- Guard -------------------------------------------------------------------

it('puts every Form Request time field on the minute grid', function () {
    $timeField = "/'(starts_at|ends_at|held_at|starts_time|ends_time)' => \[/";

    $missing = collect(File::files(app_path('Http/Requests')))
        ->filter(fn ($file) => preg_match($timeField, $file->getContents()) === 1)
        ->reject(fn ($file) => str_contains($file->getContents(), 'new OnMinuteGrid'))
        ->map(fn ($file) => $file->getFilename())
        ->values()
        ->all();

    expect($missing)->toBe([]);
});
