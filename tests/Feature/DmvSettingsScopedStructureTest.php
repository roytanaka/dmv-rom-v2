<?php

use App\Enums\Role;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\GroupMemberRole;
use App\Models\Member;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * A parent officer renames and moves a sub-group (#886, PRD #881, ADR-0036 §11). Their
 * structure form is name and parent only; the parent is any active Group in their subtree,
 * never the sub-group itself or one below it. A sibling with the same name is refused. Every
 * other field stays super-tier only, and so does their own Group.
 *
 * Asserted from outside: the HTTP response, the session errors and the stored Group.
 */

/** The org root, one Container section, Docents under it and Youth beside it. */
function restructureTree(): array
{
    $root = Group::factory()->standingCommittee()->publicListing()->create(['slug' => 'dmv', 'name' => 'DMV', 'display_order' => 0]);
    $section = Group::factory()->container()->create(['parent_id' => $root->id, 'slug' => 'programs', 'name' => 'Programs']);
    $docents = Group::factory()->program()->create(['parent_id' => $section->id, 'slug' => 'docents', 'name' => 'Docents']);
    $youth = Group::factory()->program()->create(['parent_id' => $section->id, 'slug' => 'youth', 'name' => 'Youth']);

    return [$root, $section, $docents, $youth];
}

/** A member of the given Group holding the given role. */
function restructureOfficerOf(Group $group, Role $role = Role::Chair): Member
{
    $member = Member::factory()->create();
    $membership = GroupMember::factory()->create(['group_id' => $group->id, 'member_id' => $member->id]);
    GroupMemberRole::factory()->role($role)->create(['group_member_id' => $membership->id]);

    return $member;
}

it('offers as parents the officer\'s subtree, without the sub-group, below it or archived Groups', function () {
    [, , $docents] = restructureTree();
    $sections = Group::factory()->workingGroup()->create(['parent_id' => $docents->id, 'slug' => 'docents-sections', 'name' => 'Sections', 'display_order' => 1]);
    Group::factory()->workingGroup()->create(['parent_id' => $sections->id, 'slug' => 'east', 'name' => 'East']);
    $tours = Group::factory()->workingGroup()->create(['parent_id' => $docents->id, 'slug' => 'docents-tours', 'name' => 'Tours', 'display_order' => 2]);
    Group::factory()->workingGroup()->create(['parent_id' => $tours->id, 'slug' => 'west', 'name' => 'West']);
    Group::factory()->workingGroup()->archived()->create(['parent_id' => $docents->id, 'slug' => 'old', 'name' => 'Old', 'display_order' => 3]);

    $this->actingAs(restructureOfficerOf($docents))
        ->get('/officer/settings/groups/docents-sections')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('scoped', true)
            ->where('parents', fn ($parents) => collect($parents)->map(fn ($row) => [$row['name'], $row['depth'], $row['sectionId'], $row['inactive']])->all() === [
                ['Docents', 0, $docents->id, false],
                ['Tours', 1, $docents->id, false],
                ['West', 2, $docents->id, false],
            ]));
});

it('keeps an archived current parent among the choices, marked', function () {
    [, , $docents] = restructureTree();
    $old = Group::factory()->workingGroup()->archived()->create(['parent_id' => $docents->id, 'slug' => 'old', 'name' => 'Old']);
    Group::factory()->workingGroup()->create(['parent_id' => $old->id, 'slug' => 'east', 'name' => 'East']);

    $this->actingAs(restructureOfficerOf($docents))
        ->get('/officer/settings/groups/east')
        ->assertInertia(fn (Assert $page) => $page
            ->where('parents', fn ($parents) => collect($parents)->map(fn ($row) => [$row['name'], $row['inactive']])->all() === [
                ['Docents', false],
                ['Old', true],
            ]));
});

it('lets a parent officer rename a sub-group, and names them as its last editor', function (Role $role) {
    [, , $docents] = restructureTree();
    $sections = Group::factory()->workingGroup()->create(['parent_id' => $docents->id, 'slug' => 'docents-sections', 'name' => 'Sections']);
    $officer = restructureOfficerOf($docents, $role);

    $this->actingAs($officer)
        ->patch('/officer/settings/groups/docents-sections', ['name' => 'Gallery Sections', 'parent_id' => $docents->id])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $sections->refresh();
    expect($sections->name)->toBe('Gallery Sections')
        ->and($sections->slug)->toBe('docents-sections')
        ->and($sections->updated_by)->toBe($officer->id);
})->with([Role::Chair, Role::Secretary]);

it('lets a parent officer move a sub-group anywhere in their subtree, a grandchild included', function () {
    [, , $docents] = restructureTree();
    $sections = Group::factory()->workingGroup()->create(['parent_id' => $docents->id, 'slug' => 'docents-sections', 'name' => 'Sections']);
    $tours = Group::factory()->workingGroup()->create(['parent_id' => $docents->id, 'slug' => 'docents-tours', 'name' => 'Tours']);
    $east = Group::factory()->workingGroup()->create(['parent_id' => $sections->id, 'slug' => 'east', 'name' => 'East']);
    $officer = restructureOfficerOf($docents);

    $this->actingAs($officer)
        ->patch('/officer/settings/groups/east', ['name' => 'East', 'parent_id' => $tours->id])
        ->assertSessionHasNoErrors();
    expect($east->refresh()->parent_id)->toBe($tours->id);

    $this->actingAs($officer)
        ->patch('/officer/settings/groups/east', ['name' => 'East', 'parent_id' => $docents->id])
        ->assertSessionHasNoErrors();
    expect($east->refresh()->parent_id)->toBe($docents->id);
});

it('refuses a move outside the subtree, into the sub-group itself or below it, or under an archived Group', function (string $target, string $error) {
    [, $section, $docents, $youth] = restructureTree();
    $sections = Group::factory()->workingGroup()->create(['parent_id' => $docents->id, 'slug' => 'docents-sections', 'name' => 'Sections']);
    $east = Group::factory()->workingGroup()->create(['parent_id' => $sections->id, 'slug' => 'east', 'name' => 'East']);
    $old = Group::factory()->workingGroup()->archived()->create(['parent_id' => $docents->id, 'slug' => 'old', 'name' => 'Old']);
    $ids = ['youth' => $youth->id, 'programs' => $section->id, 'self' => $sections->id, 'east' => $east->id, 'old' => $old->id, 'missing' => 999999];

    $this->actingAs(restructureOfficerOf($docents))
        ->patch('/officer/settings/groups/docents-sections', ['name' => 'Sections', 'parent_id' => $ids[$target]])
        ->assertSessionHasErrors(['parent_id' => __($error)]);

    expect($sections->refresh()->parent_id)->toBe($docents->id);
})->with([
    'another Group' => ['youth', 'dmv_settings.error.outside_subtree'],
    'the section' => ['programs', 'dmv_settings.error.outside_subtree'],
    'a missing Group' => ['missing', 'dmv_settings.error.outside_subtree'],
    'itself' => ['self', 'dmv_settings.error.parent_cycle'],
    'its descendant' => ['east', 'dmv_settings.error.parent_cycle'],
    'an archived Group' => ['old', 'dmv_settings.error.inactive_group'],
]);

it('refuses a name a sibling under the new parent already has, and allows it under another parent', function () {
    [, , $docents, $youth] = restructureTree();
    $sections = Group::factory()->workingGroup()->create(['parent_id' => $docents->id, 'slug' => 'docents-sections', 'name' => 'Sections']);
    $tours = Group::factory()->workingGroup()->create(['parent_id' => $docents->id, 'slug' => 'docents-tours', 'name' => 'Tours']);
    Group::factory()->workingGroup()->create(['parent_id' => $sections->id, 'slug' => 'east', 'name' => 'East']);
    $west = Group::factory()->workingGroup()->create(['parent_id' => $tours->id, 'slug' => 'west', 'name' => 'West']);
    Group::factory()->workingGroup()->create(['parent_id' => $youth->id, 'slug' => 'youth-west', 'name' => 'Field']);
    $officer = restructureOfficerOf($docents);

    // Renamed in place onto a sibling's name.
    $this->actingAs($officer)
        ->patch('/officer/settings/groups/docents-sections', ['name' => 'Tours', 'parent_id' => $docents->id])
        ->assertSessionHasErrors(['name' => __('dmv_settings.error.sibling_name_taken')]);
    // Moved under a parent that already has a child of that name.
    $this->actingAs($officer)
        ->patch('/officer/settings/groups/west', ['name' => 'East', 'parent_id' => $sections->id])
        ->assertSessionHasErrors(['name' => __('dmv_settings.error.sibling_name_taken')]);
    expect($west->refresh()->parent_id)->toBe($tours->id);

    // The same name under a different parent, and its own name kept, are fine.
    $this->actingAs($officer)
        ->patch('/officer/settings/groups/west', ['name' => 'East', 'parent_id' => $tours->id])
        ->assertSessionHasNoErrors();
    $this->actingAs($officer)
        ->patch('/officer/settings/groups/west', ['name' => 'Field', 'parent_id' => $tours->id])
        ->assertSessionHasNoErrors();
    $this->actingAs($officer)
        ->patch('/officer/settings/groups/docents-sections', ['name' => 'Sections', 'parent_id' => $docents->id])
        ->assertSessionHasNoErrors();
    expect($west->refresh()->name)->toBe('Field');
});

it('ignores every field beyond name and parent in a crafted request', function () {
    [, , $docents] = restructureTree();
    $sections = Group::factory()->workingGroup()->privateListing()->create([
        'parent_id' => $docents->id, 'slug' => 'docents-sections', 'name' => 'Sections', 'display_order' => 2, 'has_meetings' => true,
    ]);

    $this->actingAs(restructureOfficerOf($docents))
        ->patch('/officer/settings/groups/docents-sections', [
            'name' => 'Sections', 'parent_id' => $docents->id, 'slug' => 'hijack', 'kind' => 'program',
            'listing_visibility' => 'public', 'display_order' => 9, 'has_meetings' => false, 'has_bookings' => true,
            'time_boxed' => true, 'start_date' => '2026-01-01', 'end_date' => '2026-02-01', 'hours_multiplier' => '5',
            'lifecycle_state' => 'archived',
        ])
        ->assertSessionHasNoErrors();

    $sections->refresh();
    expect($sections->slug)->toBe('docents-sections')
        ->and($sections->kind->value)->toBe('working_group')
        ->and($sections->listing_visibility->value)->toBe('private')
        ->and($sections->display_order)->toBe(2)
        ->and($sections->has_meetings)->toBeTrue()
        ->and($sections->has_bookings)->toBeFalse()
        ->and($sections->time_boxed)->toBeFalse()
        ->and((float) $sections->hours_multiplier)->toBe(1.0)
        ->and($sections->isArchived())->toBeFalse();
});

it('refuses a parent officer their own Group, a Group outside their subtree and an archived sub-group', function () {
    [, , $docents, $youth] = restructureTree();
    Group::factory()->workingGroup()->create(['parent_id' => $youth->id, 'slug' => 'youth-sections', 'name' => 'Sections']);
    Group::factory()->workingGroup()->archived()->create(['parent_id' => $docents->id, 'slug' => 'old', 'name' => 'Old']);
    $officer = restructureOfficerOf($docents);

    $this->actingAs($officer)->patch('/officer/settings/groups/docents', ['name' => 'Renamed', 'parent_id' => $docents->parent_id])->assertForbidden();
    $this->actingAs($officer)->patch('/officer/settings/groups/youth-sections', ['name' => 'Renamed', 'parent_id' => $youth->id])->assertForbidden();
    $this->actingAs($officer)
        ->patch('/officer/settings/groups/old', ['name' => 'Renamed', 'parent_id' => $docents->id])
        ->assertSessionHasErrors(['group' => __('dmv_settings.error.archived')]);

    expect($docents->refresh()->name)->toBe('Docents')
        ->and(Group::query()->where('slug', 'youth-sections')->value('name'))->toBe('Sections')
        ->and(Group::query()->where('slug', 'old')->value('name'))->toBe('Old');
});
