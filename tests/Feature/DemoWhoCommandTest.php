<?php

use App\Enums\MembershipStatus;
use App\Enums\Role;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\GroupMemberRole;
use App\Models\Member;

/*
 * `demo:who <group-slug>` (#807) — finds a demo account by its state for a verify
 * run: each membership's email, status and roles, read from the live database.
 */

it('lists each membership with its email, status and roles', function () {
    $group = Group::factory()->program()->create(['slug' => 'docents']);

    $chair = GroupMember::factory()->create([
        'group_id' => $group->id,
        'member_id' => Member::factory()->create(['email' => 'chair@dmv.test'])->id,
        'status' => MembershipStatus::Full,
    ]);
    GroupMemberRole::factory()->create(['group_member_id' => $chair->id, 'role' => Role::Chair]);
    GroupMemberRole::factory()->create(['group_member_id' => $chair->id, 'role' => Role::Scheduler]);

    GroupMember::factory()->create([
        'group_id' => $group->id,
        'member_id' => Member::factory()->create(['email' => 'trainee@dmv.test'])->id,
        'status' => MembershipStatus::Trainee,
    ]);

    $this->artisan('demo:who docents')
        ->expectsTable(['Email', 'Status', 'Roles'], [
            ['chair@dmv.test', 'full', 'chair, scheduler'],
            ['trainee@dmv.test', 'trainee', ''],
        ])
        ->assertSuccessful();
});

it('fails on an unknown group slug', function () {
    $this->artisan('demo:who nowhere')
        ->expectsOutputToContain('No Group with slug "nowhere"')
        ->assertFailed();
});
