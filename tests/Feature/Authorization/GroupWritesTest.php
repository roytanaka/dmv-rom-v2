<?php

use App\Models\Document;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\Meeting;
use App\Models\Member;
use App\Models\Schedule;
use App\Models\Shift;
use App\Models\SignUp;
use App\Support\Authorization\GroupWrites;

/*
 * The inactive-Group read-only rule's resolver (#839, ADR-0036 §2): which Gate abilities count
 * as writes, and which Group a checked subject belongs to. Asserted at the class seam, plus one
 * Gate-level check that the rule sits ahead of the super-tier grant.
 */

it('names every content-changing ability a write and nothing else', function () {
    foreach (['create', 'update', 'delete', 'publish', 'move', 'manage', 'assign', 'record', 'recalculate', 'sendMails'] as $write) {
        expect(GroupWrites::isWrite($write))->toBeTrue($write);
    }

    foreach (['view', 'viewAny', 'viewReports', 'download', 'viewContact', 'administer-members', 'administer-groups'] as $read) {
        expect(GroupWrites::isWrite($read))->toBeFalse($read);
    }
});

it('resolves the owning Group from the first Model argument, skipping a leading class-string', function () {
    $group = Group::factory()->program()->create();
    $schedule = Schedule::factory()->create(['group_id' => $group->id]);
    $shift = Shift::factory()->create(['schedule_id' => $schedule->id]);
    $member = Member::factory()->create();
    $signUp = SignUp::factory()->create(['shift_id' => $shift->id, 'member_id' => $member->id]);
    $meeting = Meeting::factory()->create(['group_id' => $group->id]);
    $document = Document::factory()->create(['group_id' => $group->id]);
    $membership = GroupMember::factory()->create(['group_id' => $group->id, 'member_id' => $member->id]);

    expect(GroupWrites::groupFor([$group])?->id)->toBe($group->id)
        ->and(GroupWrites::groupFor([Shift::class, $schedule])?->id)->toBe($group->id)
        ->and(GroupWrites::groupFor([$shift])?->id)->toBe($group->id)
        ->and(GroupWrites::groupFor([$shift, $member])?->id)->toBe($group->id)
        ->and(GroupWrites::groupFor([$signUp])?->id)->toBe($group->id)
        ->and(GroupWrites::groupFor([$meeting])?->id)->toBe($group->id)
        ->and(GroupWrites::groupFor([$document])?->id)->toBe($group->id)
        ->and(GroupWrites::groupFor([$membership])?->id)->toBe($group->id);
});

it('resolves no Group for a Group-less check, so those abilities are never refused', function () {
    $member = Member::factory()->create();

    expect(GroupWrites::groupFor([]))->toBeNull()
        ->and(GroupWrites::groupFor([$member]))->toBeNull()
        ->and(GroupWrites::groupFor([Group::class]))->toBeNull()
        ->and(GroupWrites::refuses('update', [$member]))->toBeFalse()
        ->and(GroupWrites::refuses('administer-groups', []))->toBeFalse();
});

it('refuses a write, never a read, on an archived or expired Group only', function () {
    $active = Group::factory()->create();
    $archived = Group::factory()->archived()->create();
    $expired = Group::factory()->timeBoxed()->create(['end_date' => now()->subDay()->toDateString()]);
    $running = Group::factory()->timeBoxed()->create(['end_date' => now()->toDateString()]);

    expect(GroupWrites::refuses('update', [$active]))->toBeFalse()
        ->and(GroupWrites::refuses('update', [$running]))->toBeFalse()
        ->and(GroupWrites::refuses('update', [$archived]))->toBeTrue()
        ->and(GroupWrites::refuses('update', [$expired]))->toBeTrue()
        ->and(GroupWrites::refuses('view', [$archived]))->toBeFalse()
        ->and(GroupWrites::refuses('view', [$expired]))->toBeFalse();
});

it('binds the super-tier at the Gate: a write on an archived Group is denied, a read granted', function () {
    $archived = Group::factory()->workingGroup()->archived()->create();
    $superTier = Member::factory()->superTier()->create();

    expect($superTier->can('update', $archived))->toBeFalse()
        ->and($superTier->can('create', [Meeting::class, $archived]))->toBeFalse()
        ->and($superTier->can('viewAny', [Meeting::class, $archived]))->toBeTrue()
        ->and($superTier->can('administer-members'))->toBeTrue();
});
