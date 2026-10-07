<?php

use App\Enums\Role;
use App\Models\Document;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\GroupMemberRole;
use App\Models\Member;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * Documents for every sub-group (#723, #308, ADR-0030 §6). Working groups and Cohorts run
 * a Document library like Programs, Committees and Projects; Containers stay off. A parent
 * Group's Chair reads a sub-group's Documents only after joining it (ADR-0019).
 */

beforeEach(function () {
    Storage::fake('local');
});

function subgroupMemberOf(Group $group, ?Role $role = null): Member
{
    $member = Member::factory()->create();
    $membership = GroupMember::factory()->create(['group_id' => $group->id, 'member_id' => $member->id]);

    if ($role !== null) {
        GroupMemberRole::factory()->role($role)->create(['group_member_id' => $membership->id]);
    }

    return $member;
}

dataset('sub-group kinds', [
    'Working group' => [fn () => Group::factory()->workingGroup()],
    'Cohort' => [fn () => Group::factory()->cohort()],
]);

it('shows a sub-group the Documents tab and lets its Chair assign the Librarian role', function (Closure $factory) {
    $group = $factory()->create();
    $chair = subgroupMemberOf($group, Role::Chair);

    $this->actingAs($chair)
        ->get(route('groups.show', ['group' => $group, 'section' => 'documents']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('group.capabilities.documents', true)
            ->where('can.manageDocuments', true));

    $this->actingAs($chair)
        ->get(route('groups.show', ['group' => $group, 'section' => 'roster']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('rosterMeta.assignableRoles', fn (Collection $roles) => $roles->contains(Role::Librarian->value)));
})->with('sub-group kinds');

it('keeps the documents capability off for a Container', function () {
    $container = Group::factory()->container()->create();

    $this->actingAs(Member::factory()->superTier()->create())
        ->get(route('groups.show', ['group' => $container, 'section' => 'documents']))
        ->assertNotFound();
});

it('refuses a parent Group\'s Chair a sub-group\'s Document until the Chair joins it', function (Closure $factory) {
    $parent = Group::factory()->program()->create();
    $child = $factory()->create(['parent_id' => $parent->id]);
    $document = Document::factory()->create(['group_id' => $child->id]);
    Storage::disk('local')->put($document->storage_path, 'bytes');
    $chair = subgroupMemberOf($parent, Role::Chair);

    $this->actingAs($chair)
        ->get(route('documents.download', $document))
        ->assertForbidden();

    $this->actingAs($chair)
        ->get(route('groups.show', ['group' => $child, 'section' => 'documents']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('can.manageDocuments', false));

    GroupMember::factory()->create(['group_id' => $child->id, 'member_id' => $chair->id]);

    $this->actingAs($chair->fresh())
        ->get(route('documents.download', $document))
        ->assertOk();
})->with('sub-group kinds');
