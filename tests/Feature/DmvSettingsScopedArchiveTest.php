<?php

use App\Enums\LifecycleState;
use App\Enums\Role;
use App\Enums\StewardshipFunction;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\GroupMemberRole;
use App\Models\Member;
use Illuminate\Support\Collection;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * A parent officer archives and unarchives a sub-group (#887, PRD #881, ADR-0036 §2 and §11),
 * at any depth below a Group they administer. The stewardship guard applies to them as it does
 * to the super-tier. Their own Group and Groups outside their subtree stay out of reach.
 * Unarchive must not trip the inactive-Group write rule in Gate::before.
 *
 * Asserted from outside: the HTTP response, the session errors, the stored Group, the scoped
 * tree and form, and the rail.
 */

/** The org root, one Container section, Docents under it with Sections below, and Youth beside it. */
function archiveScopedTree(): array
{
    $root = Group::factory()->standingCommittee()->publicListing()->create(['slug' => 'dmv', 'name' => 'DMV', 'display_order' => 0]);
    $section = Group::factory()->container()->create(['parent_id' => $root->id, 'slug' => 'programs', 'name' => 'Programs']);
    $docents = Group::factory()->program()->create(['parent_id' => $section->id, 'slug' => 'docents', 'name' => 'Docents']);
    $youth = Group::factory()->program()->create(['parent_id' => $section->id, 'slug' => 'youth', 'name' => 'Youth']);
    $sections = Group::factory()->workingGroup()->create(['parent_id' => $docents->id, 'slug' => 'docents-sections', 'name' => 'Sections']);

    return [$docents, $youth, $sections];
}

/** A member of the given Group holding the given role. */
function archiveScopedOfficerOf(Group $group, Role $role = Role::Chair): Member
{
    $member = Member::factory()->create();
    $membership = GroupMember::factory()->create(['group_id' => $group->id, 'member_id' => $member->id]);
    GroupMemberRole::factory()->role($role)->create(['group_member_id' => $membership->id]);

    return $member;
}

it('lets a parent officer archive a sub-group, which leaves the rail and becomes read-only', function (Role $role) {
    [$docents, , $sections] = archiveScopedTree();
    $officer = archiveScopedOfficerOf($docents, $role);
    $member = Member::factory()->create();
    GroupMember::factory()->create(['group_id' => $sections->id, 'member_id' => $member->id]);
    $listed = fn (Collection $items) => $items->pluck('groupId')->contains('docents-sections');

    $this->actingAs($member->fresh())->get('/dashboard')->assertInertia(fn (Assert $page) => $page->where('rail.myGroups.items', $listed));

    $this->actingAs($officer)
        ->from('/officer/settings/groups/docents-sections')
        ->patch('/officer/settings/groups/docents-sections/archive')
        ->assertSessionHasNoErrors()
        ->assertRedirect('/officer/settings/groups/docents-sections');

    $sections->refresh();
    expect($sections->lifecycle_state)->toBe(LifecycleState::Archived)
        ->and($sections->updatedBy->is($officer))->toBeTrue();

    $this->actingAs($member->fresh())->get('/dashboard')->assertInertia(fn (Assert $page) => $page->where(
        'rail.myGroups',
        fn ($myGroups) => ! collect($myGroups['items'] ?? [])->pluck('groupId')->contains('docents-sections'),
    ));
    $this->actingAs($officer)
        ->patch('/officer/settings/groups/docents-sections', ['name' => 'Renamed', 'parent_id' => $docents->id])
        ->assertSessionHasErrors(['group' => __('dmv_settings.error.archived')]);
})->with([Role::Chair, Role::Secretary]);

it('lets a parent officer and a grandparent officer unarchive an archived sub-group, listed and open while archived', function (string $level) {
    [$docents, , $sections] = archiveScopedTree();
    $east = Group::factory()->workingGroup()->archived()->create(['parent_id' => $sections->id, 'slug' => 'east', 'name' => 'East']);
    $officer = archiveScopedOfficerOf($level === 'parent' ? $sections : $docents);

    $this->actingAs($officer)
        ->get('/officer/settings')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('can.administerGroups', false)
            ->where('tree', fn ($tree) => collect($tree)->contains(fn ($row) => $row['slug'] === 'east' && $row['archived'] && $row['href'] !== null)));
    $this->actingAs($officer)
        ->get('/officer/settings/groups/east')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('group.archived', true)->where('can.administerGroups', false));

    $this->actingAs($officer)
        ->from('/officer/settings/groups/east')
        ->patch('/officer/settings/groups/east/unarchive')
        ->assertSessionHasNoErrors()
        ->assertRedirect('/officer/settings/groups/east');

    $east->refresh();
    expect($east->lifecycle_state)->toBe(LifecycleState::Active)
        ->and($east->updatedBy->is($officer))->toBeTrue();
})->with(['parent', 'grandparent']);

it('lets a grandparent officer archive a grandchild', function () {
    [$docents, , $sections] = archiveScopedTree();
    $east = Group::factory()->workingGroup()->create(['parent_id' => $sections->id, 'slug' => 'east', 'name' => 'East']);

    $this->actingAs(archiveScopedOfficerOf($docents))
        ->patch('/officer/settings/groups/east/archive')
        ->assertSessionHasNoErrors();

    expect($east->fresh()->lifecycle_state)->toBe(LifecycleState::Archived);
});

it('refuses a parent officer archiving a sub-group that holds a stewardship', function () {
    [$docents, , $sections] = archiveScopedTree();
    $sections->stewardships()->create(['function' => StewardshipFunction::MemberAdmin]);

    $this->actingAs(archiveScopedOfficerOf($docents))
        ->patch('/officer/settings/groups/docents-sections/archive')
        ->assertSessionHasErrors(['archive' => 'Sections holds Member administration. Move the Stewardship first.']);

    expect($sections->fresh()->lifecycle_state)->toBe(LifecycleState::Active);
});

it('forbids a parent officer archiving or unarchiving their own Group or a Group outside their subtree', function (string $action) {
    [$docents, $youth] = archiveScopedTree();
    $state = $action === 'archive' ? LifecycleState::Active : LifecycleState::Archived;
    $docents->update(['lifecycle_state' => $state]);
    $youthSections = Group::factory()->workingGroup()->create(['parent_id' => $youth->id, 'slug' => 'youth-sections', 'lifecycle_state' => $state]);
    $officer = archiveScopedOfficerOf($docents);

    $this->actingAs($officer)->patch("/officer/settings/groups/docents/{$action}")->assertForbidden();
    $this->actingAs($officer)->patch("/officer/settings/groups/youth-sections/{$action}")->assertForbidden();
    $this->actingAs($officer)->patch("/officer/settings/groups/programs/{$action}")->assertForbidden();

    expect($docents->fresh()->lifecycle_state)->toBe($state)
        ->and($youthSections->fresh()->lifecycle_state)->toBe($state);
})->with(['archive', 'unarchive']);
