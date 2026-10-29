<?php

use App\Enums\Role;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\GroupMemberRole;
use App\Models\Member;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * DMV Settings opens to parent officers, scoped to their sub-groups (#883, PRD #881,
 * ADR-0036 §11). A Group's Secretary or Chair reaches DMV Settings; its Groups section lists
 * only the subtrees below the Groups they administer, their own Group heading each one as a
 * row they cannot open. Stewardships stay super-tier only. The super-tier's page is unchanged.
 *
 * Asserted from outside: the HTTP response, the Inertia props and the shared rail prop.
 */

/** The org root, with one Container section under it. */
function scopedRootAndSection(): array
{
    $root = Group::factory()->standingCommittee()->publicListing()->create(['slug' => 'dmv', 'name' => 'DMV', 'display_order' => 0]);
    $section = Group::factory()->container()->create(['parent_id' => $root->id, 'slug' => 'programs', 'name' => 'Programs']);

    return [$root, $section];
}

/** A member of the given Group holding the given role. */
function scopedOfficerOf(Group $group, Role $role = Role::Chair, ?Member $member = null): Member
{
    $member ??= Member::factory()->create();
    $membership = GroupMember::factory()->create(['group_id' => $group->id, 'member_id' => $member->id]);
    GroupMemberRole::factory()->role($role)->create(['group_member_id' => $membership->id]);

    return $member;
}

// --- Entry and rail ------------------------------------------------------------------------

it('lets a Chair and a Secretary reach DMV Settings and see its rail item', function (Role $role) {
    [, $section] = scopedRootAndSection();
    $docents = Group::factory()->program()->create(['parent_id' => $section->id]);
    $officer = scopedOfficerOf($docents, $role);

    $this->actingAs($officer)
        ->get('/officer/settings')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('officer/settings/Groups'));

    $this->actingAs($officer)
        ->get('/dashboard')
        ->assertInertia(fn (Assert $page) => $page->where(
            'rail',
            fn ($rail) => collect(data_get($rail, 'officer.items', []))->contains('key', 'settings'),
        ));
})->with([Role::Chair, Role::Secretary]);

it('refuses a plain Member and a non-officer role holder, and hides the rail item', function (Role|string $authority) {
    [, $section] = scopedRootAndSection();
    $group = Group::factory()->program()->create(['parent_id' => $section->id]);
    $member = $authority === 'plain' ? Member::factory()->create() : scopedOfficerOf($group, $authority);

    $this->actingAs($member)->get('/officer/settings')->assertForbidden();

    $this->actingAs($member)
        ->get('/dashboard')
        ->assertInertia(fn (Assert $page) => $page->where(
            'rail',
            fn ($rail) => collect(data_get($rail, 'officer.items', []))->doesntContain('key', 'settings'),
        ));
})->with(['plain' => 'plain', 'librarian' => Role::Librarian]);

// --- Scoped tree ---------------------------------------------------------------------------

it('lists only the subtree below a parent officer\'s Group, archived sub-groups included', function () {
    [, $section] = scopedRootAndSection();
    $docents = Group::factory()->program()->create(['parent_id' => $section->id, 'slug' => 'docents', 'name' => 'Docents']);
    Group::factory()->program()->create(['parent_id' => $section->id, 'slug' => 'youth', 'name' => 'Youth']);
    $sections = Group::factory()->workingGroup()->create(['parent_id' => $docents->id, 'slug' => 'docents-sections', 'name' => 'Sections', 'display_order' => 1]);
    Group::factory()->workingGroup()->archived()->create(['parent_id' => $sections->id, 'slug' => 'old-section', 'name' => 'Old Section']);
    Group::factory()->workingGroup()->create(['parent_id' => $docents->id, 'slug' => 'docents-tours', 'name' => 'Tours', 'display_order' => 2]);

    $this->actingAs(scopedOfficerOf($docents, Role::Secretary))
        ->get('/officer/settings')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('can.administerGroups', false)
            ->where('tree', fn ($tree) => collect($tree)->map(fn ($row) => [$row['slug'], $row['depth'], $row['head'], $row['href'] !== null, $row['archived']])->all() === [
                ['docents', 0, true, false, false],
                ['docents-sections', 1, false, true, false],
                ['old-section', 2, false, true, true],
                ['docents-tours', 1, false, true, false],
            ])
            ->where('parentOptions', [])
            ->where('kinds', []));
});

it('lists both subtrees for a parent officer of two Groups, and a nested one once', function () {
    [, $section] = scopedRootAndSection();
    $docents = Group::factory()->program()->create(['parent_id' => $section->id, 'slug' => 'docents', 'name' => 'Docents', 'display_order' => 1]);
    $guides = Group::factory()->program()->create(['parent_id' => $section->id, 'slug' => 'guides', 'name' => 'Guides', 'display_order' => 2]);
    Group::factory()->program()->create(['parent_id' => $section->id, 'slug' => 'youth', 'name' => 'Youth', 'display_order' => 3]);
    $sections = Group::factory()->workingGroup()->create(['parent_id' => $docents->id, 'slug' => 'docents-sections', 'name' => 'Sections']);
    Group::factory()->workingGroup()->create(['parent_id' => $guides->id, 'slug' => 'guides-sections', 'name' => 'Sections']);

    $officer = scopedOfficerOf($docents);
    scopedOfficerOf($guides, Role::Secretary, $officer);
    scopedOfficerOf($sections, Role::Chair, $officer);

    $this->actingAs($officer)
        ->get('/officer/settings')
        ->assertInertia(fn (Assert $page) => $page
            ->where('tree', fn ($tree) => collect($tree)->map(fn ($row) => [$row['slug'], $row['depth'], $row['head']])->all() === [
                ['docents', 0, true],
                ['docents-sections', 1, false],
                ['guides', 0, true],
                ['guides-sections', 1, false],
            ]));
});

it('shows the super-tier the whole tree as before', function () {
    [$root, $section] = scopedRootAndSection();
    Group::factory()->program()->create(['parent_id' => $section->id, 'slug' => 'docents', 'name' => 'Docents']);

    $this->actingAs(Member::factory()->superTier()->create())
        ->get('/officer/settings')
        ->assertInertia(fn (Assert $page) => $page
            ->where('can.administerGroups', true)
            ->where('tree', fn ($tree) => collect($tree)->map(fn ($row) => [$row['slug'], $row['depth'], $row['head']])->all() === [
                ['dmv', 0, false],
                ['programs', 1, false],
                ['docents', 2, false],
            ])
            ->has('parentOptions', 2)
            ->where('kinds', fn ($kinds) => count($kinds) > 0));
});

// --- Structure form and refusals -----------------------------------------------------------

it('opens a sub-group to a parent officer at any depth, archived included', function () {
    [, $section] = scopedRootAndSection();
    $docents = Group::factory()->program()->create(['parent_id' => $section->id, 'slug' => 'docents']);
    $sections = Group::factory()->workingGroup()->create(['parent_id' => $docents->id, 'slug' => 'docents-sections']);
    Group::factory()->workingGroup()->archived()->create(['parent_id' => $sections->id, 'slug' => 'old-section']);
    $officer = scopedOfficerOf($docents);

    foreach (['docents-sections', 'old-section'] as $slug) {
        $this->actingAs($officer)
            ->get("/officer/settings/groups/{$slug}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('officer/settings/Group')
                ->where('group.slug', $slug)
                ->where('can.administerGroups', false));
    }
});

it('refuses a parent officer their own Group, a Group outside their subtree, the section and the root', function () {
    [, $section] = scopedRootAndSection();
    $docents = Group::factory()->program()->create(['parent_id' => $section->id, 'slug' => 'docents']);
    $youth = Group::factory()->program()->create(['parent_id' => $section->id, 'slug' => 'youth']);
    Group::factory()->workingGroup()->create(['parent_id' => $youth->id, 'slug' => 'youth-sections']);
    $officer = scopedOfficerOf($docents);

    foreach (['docents', 'youth', 'youth-sections', 'programs', 'dmv'] as $slug) {
        $this->actingAs($officer)->get("/officer/settings/groups/{$slug}")->assertForbidden();
    }
});

it('refuses a sub-group to an officer of a sibling Group', function () {
    [, $section] = scopedRootAndSection();
    $docents = Group::factory()->program()->create(['parent_id' => $section->id, 'slug' => 'docents']);
    Group::factory()->workingGroup()->create(['parent_id' => $docents->id, 'slug' => 'docents-sections']);
    $tours = Group::factory()->workingGroup()->create(['parent_id' => $docents->id, 'slug' => 'docents-tours']);

    $this->actingAs(scopedOfficerOf($tours))->get('/officer/settings/groups/docents-sections')->assertForbidden();
});

it('refuses a parent officer the Stewardships section and its endpoints', function () {
    [, $section] = scopedRootAndSection();
    $docents = Group::factory()->program()->create(['parent_id' => $section->id, 'slug' => 'docents']);
    $officer = scopedOfficerOf($docents);

    $this->actingAs($officer)->get('/officer/settings/stewardships')->assertForbidden();
    $this->actingAs($officer)
        ->put('/officer/settings/stewardships/member_admin', ['group_id' => $docents->id])
        ->assertForbidden();
});

it('keeps the super-tier structure form unscoped', function () {
    [, $section] = scopedRootAndSection();
    Group::factory()->program()->create(['parent_id' => $section->id, 'slug' => 'docents']);

    $this->actingAs(Member::factory()->superTier()->create())
        ->get('/officer/settings/groups/docents')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('can.administerGroups', true));
});

// --- Inactive Groups -----------------------------------------------------------------------

it('gives an officer of an archived or ended Group no DMV Settings and no sub-group form', function (string $state) {
    [, $section] = scopedRootAndSection();
    $factory = Group::factory()->program();
    $retired = ($state === 'archived' ? $factory->archived() : $factory->state(['time_boxed' => true, 'start_date' => '2020-01-01', 'end_date' => '2020-12-31']))
        ->create(['parent_id' => $section->id, 'slug' => 'retired']);
    Group::factory()->workingGroup()->create(['parent_id' => $retired->id, 'slug' => 'retired-sub']);
    $officer = scopedOfficerOf($retired);

    $this->actingAs($officer)->get('/officer/settings')->assertForbidden();
    $this->actingAs($officer)->get('/officer/settings/groups/retired-sub')->assertForbidden();
    $this->actingAs($officer)
        ->get('/dashboard')
        ->assertInertia(fn (Assert $page) => $page->where(
            'rail',
            fn ($rail) => collect(data_get($rail, 'officer.items', []))->doesntContain('key', 'settings'),
        ));
})->with(['archived', 'ended']);

it('keeps an active grandparent officer\'s authority across an archived Group between', function () {
    [, $section] = scopedRootAndSection();
    $docents = Group::factory()->program()->create(['parent_id' => $section->id, 'slug' => 'docents']);
    $old = Group::factory()->workingGroup()->archived()->create(['parent_id' => $docents->id, 'slug' => 'old']);
    Group::factory()->workingGroup()->create(['parent_id' => $old->id, 'slug' => 'old-sub']);

    $this->actingAs(scopedOfficerOf($docents))->get('/officer/settings/groups/old-sub')->assertOk();
    $this->actingAs(scopedOfficerOf($old))->get('/officer/settings/groups/old-sub')->assertForbidden();
});
