<?php

use App\Enums\MembershipStatus;
use App\Enums\Role;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\GroupMemberRole;
use App\Models\Member;
use App\Models\Qualification;
use App\Models\Tour;

/*
 * `demo:who <group-slug>` (#807) — finds a demo account by its state for a verify
 * run: each membership's email, status, roles and active qualification count, read
 * from the live database.
 */

it('lists each membership with its email, status, roles and active qualifications', function () {
    $group = Group::factory()->program()->create(['slug' => 'docents', 'has_vetting' => true]);

    $chair = GroupMember::factory()->create([
        'group_id' => $group->id,
        'member_id' => Member::factory()->create(['email' => 'chair@dmv.test'])->id,
        'status' => MembershipStatus::Full,
    ]);
    GroupMemberRole::factory()->create(['group_member_id' => $chair->id, 'role' => Role::Chair]);
    GroupMemberRole::factory()->create(['group_member_id' => $chair->id, 'role' => Role::Scheduler]);
    foreach (Tour::factory()->count(3)->create(['group_id' => $group->id]) as $i => $tour) {
        Qualification::factory()->create([
            'group_member_id' => $chair->id,
            'tour_id' => $tour->id,
            'active' => $i < 2,
        ]);
    }

    GroupMember::factory()->create([
        'group_id' => $group->id,
        'member_id' => Member::factory()->create(['email' => 'trainee@dmv.test'])->id,
        'status' => MembershipStatus::Trainee,
    ]);

    $this->artisan('demo:who docents')
        ->expectsTable(['Email', 'Status', 'Roles', 'Active qualifications'], [
            ['chair@dmv.test', 'full', 'chair, scheduler', '2'],
            ['trainee@dmv.test', 'trainee', '', '0'],
        ])
        ->assertSuccessful();
});

it('fails on an unknown group slug', function () {
    $this->artisan('demo:who nowhere')
        ->expectsOutputToContain('No Group with slug "nowhere"')
        ->assertFailed();
});
