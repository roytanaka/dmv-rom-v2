<?php

use App\Enums\Role;
use App\Enums\StewardshipFunction;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\GroupMemberRole;
use App\Models\Member;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * Edit a Group's structure from DMV Settings (#843, PRD #836, ADR-0036 §5 and §8): the
 * super-tier changes a Group's name, place in the tree, kind, listing, dates, capability and
 * collection flags and hours multiplier on one form. The slug never changes. The root and
 * Containers have no form; an archived Group's form is read-only until it is unarchived.
 *
 * Asserted from outside: the HTTP response, the Inertia props, the session errors, the stored
 * Group and the rail on the next request.
 */

/** The DMV root, inline, as the tree has it. */
function structureRoot(): Group
{
    return Group::factory()->standingCommittee()->publicListing()->create(['slug' => 'dmv', 'name' => 'DMV', 'display_order' => 0]);
}

/** A plain Member, a Records Member, or a Member holding $role in some Group. */
function structureActor(Role|string $authority): Member
{
    $member = Member::factory()->create();
    if ($authority === 'plain') {
        return $member;
    }

    $group = Group::factory()->program()->create();
    $membership = GroupMember::factory()->create(['group_id' => $group->id, 'member_id' => $member->id]);
    if ($authority === 'records') {
        $group->stewardships()->create(['function' => StewardshipFunction::MemberAdmin]);
    } else {
        GroupMemberRole::factory()->role($authority)->create(['group_member_id' => $membership->id]);
    }

    return $member;
}

/** Every editable field, at a value different from the factory default. */
function structurePayload(Group $parent, array $overrides = []): array
{
    return [
        'name' => 'Youth Tours Renamed',
        'parent_id' => $parent->id,
        'kind' => 'working_group',
        'listing_visibility' => 'private',
        'display_order' => 7,
        'logo_key' => 'romwalks',
        'time_boxed' => true,
        'start_date' => '2026-09-01',
        'end_date' => '2027-06-30',
        'has_meetings' => true,
        'has_documents' => true,
        'has_scheduling' => true,
        'has_vetting' => true,
        'has_announcements' => true,
        'has_bookings' => true,
        'collects_visitor_count' => true,
        'collects_extra_interactions' => true,
        'collects_visitor_provenance' => true,
        'visitor_figures_await_booking' => true,
        'hours_multiplier' => '1.333',
        ...$overrides,
    ];
}

// --- The page -------------------------------------------------------------------------

it('sends the Group\'s editable fields to the form', function () {
    $root = structureRoot();
    Group::factory()->create([
        'kind' => 'working_group', 'parent_id' => $root->id, 'slug' => 'youth-tours', 'name' => 'Youth Tours', 'display_order' => 3,
        'logo_key' => 'docents', 'listing_visibility' => 'public', 'has_meetings' => true,
        'collects_visitor_count' => true, 'hours_multiplier' => 2,
    ]);

    $this->actingAs(Member::factory()->superTier()->create())
        ->get('/officer/settings/groups/youth-tours')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('officer/settings/Group')
            ->where('group.slug', 'youth-tours')
            ->where('group.name', 'Youth Tours')
            ->where('group.parent_id', $root->id)
            ->where('group.kind', 'working_group')
            ->where('group.listing_visibility', 'public')
            ->where('group.display_order', 3)
            ->where('group.logo_key', 'docents')
            ->where('group.time_boxed', false)
            ->where('group.start_date', null)
            ->where('group.end_date', null)
            ->where('group.has_meetings', true)
            ->where('group.has_documents', false)
            ->where('group.has_scheduling', false)
            ->where('group.has_vetting', false)
            ->where('group.has_announcements', false)
            ->where('group.has_bookings', false)
            ->where('group.collects_visitor_count', true)
            ->where('group.collects_extra_interactions', false)
            ->where('group.collects_visitor_provenance', false)
            ->where('group.visitor_figures_await_booking', false)
            ->where('group.hours_multiplier', '2.000')
            ->where('group.archived', false)
            ->where('group.endedOn', null)
            ->where('kinds', ['standing_committee', 'program', 'working_group', 'project', 'cohort', 'container'])
            ->where('visibilities', ['public', 'group', 'private'])
            ->has('logos', 16)
            ->where('logos.0', 'docents')
            ->where('lastChanged', null));
});

it('offers active Groups as parents, minus the Group itself and everything under it', function () {
    $root = structureRoot();
    $programs = Group::factory()->container()->create(['parent_id' => $root->id, 'name' => 'Programs', 'display_order' => 1]);
    $docents = Group::factory()->program()->create(['parent_id' => $programs->id, 'slug' => 'docents', 'name' => 'Docents']);
    $cohort = Group::factory()->cohort()->create(['parent_id' => $docents->id, 'name' => 'Class of 2026']);
    Group::factory()->workingGroup()->create(['parent_id' => $cohort->id, 'name' => 'Cohort Committee']);
    Group::factory()->program()->archived()->create(['parent_id' => $programs->id, 'name' => 'Archived Tours']);
    $romwalks = Group::factory()->program()->create(['parent_id' => $programs->id, 'name' => 'ROMWalks']);

    $this->actingAs(Member::factory()->superTier()->create())
        ->get('/officer/settings/groups/docents')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('parents', [
                ['id' => $root->id, 'name' => 'DMV', 'depth' => 0, 'inactive' => false],
                ['id' => $programs->id, 'name' => 'Programs', 'depth' => 1, 'inactive' => false],
                ['id' => $romwalks->id, 'name' => 'ROMWalks', 'depth' => 2, 'inactive' => false],
            ]));
});

it('has no form for the root or a Container', function () {
    $root = structureRoot();
    Group::factory()->container()->create(['parent_id' => $root->id, 'slug' => 'programs']);
    $actor = Member::factory()->superTier()->create();

    $this->actingAs($actor)->get('/officer/settings/groups/dmv')->assertNotFound();
    $this->actingAs($actor)->get('/officer/settings/groups/programs')->assertNotFound();
});

it('marks an archived or ended Group for the h1 badges', function () {
    $root = structureRoot();
    Group::factory()->program()->archived()->create(['parent_id' => $root->id, 'slug' => 'old-tours']);
    Group::factory()->cohort()->timeBoxed()->create(['parent_id' => $root->id, 'slug' => 'class-2024', 'end_date' => '2025-06-30']);
    $actor = Member::factory()->superTier()->create();

    $this->actingAs($actor)->get('/officer/settings/groups/old-tours')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('group.archived', true)->where('group.endedOn', null));

    $this->actingAs($actor)->get('/officer/settings/groups/class-2024')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('group.archived', false)->where('group.endedOn', '2025-06-30'));
});

it('forbids the page to anyone below the super-tier', function (Role|string $authority) {
    Group::factory()->program()->create(['slug' => 'youth-tours']);

    $this->actingAs(structureActor($authority))
        ->get('/officer/settings/groups/youth-tours')
        ->assertForbidden();
})->with(['plain', 'records', Role::Chair]);

// --- Saving ---------------------------------------------------------------------------

it('saves every field and returns to the form', function () {
    $root = structureRoot();
    $governance = Group::factory()->standingCommittee()->create(['parent_id' => $root->id, 'name' => 'Governance']);
    $group = Group::factory()->program()->create(['parent_id' => $root->id, 'slug' => 'youth-tours', 'name' => 'Youth Tours']);

    $this->actingAs(Member::factory()->superTier()->create())
        ->from('/officer/settings/groups/youth-tours')
        ->patch('/officer/settings/groups/youth-tours', structurePayload($governance))
        ->assertSessionHasNoErrors()
        ->assertRedirect('/officer/settings/groups/youth-tours');

    $group->refresh();
    expect($group->name)->toBe('Youth Tours Renamed')
        ->and($group->parent_id)->toBe($governance->id)
        ->and($group->kind->value)->toBe('working_group')
        ->and($group->listing_visibility->value)->toBe('private')
        ->and($group->display_order)->toBe(7)
        ->and($group->logo_key->value)->toBe('romwalks')
        ->and($group->time_boxed)->toBeTrue()
        ->and($group->start_date->toDateString())->toBe('2026-09-01')
        ->and($group->end_date->toDateString())->toBe('2027-06-30')
        ->and($group->has_meetings)->toBeTrue()
        ->and($group->has_documents)->toBeTrue()
        ->and($group->has_scheduling)->toBeTrue()
        ->and($group->has_vetting)->toBeTrue()
        ->and($group->has_announcements)->toBeTrue()
        ->and($group->has_bookings)->toBeTrue()
        ->and($group->collects_visitor_count)->toBeTrue()
        ->and($group->collects_extra_interactions)->toBeTrue()
        ->and($group->collects_visitor_provenance)->toBeTrue()
        ->and($group->visitor_figures_await_booking)->toBeTrue()
        ->and($group->hours_multiplier)->toBe('1.333');
});

it('switches flags off, clears the logo and drops the dates when no longer time-boxed', function () {
    $root = structureRoot();
    $group = Group::factory()->program()->timeBoxed()->create(['parent_id' => $root->id, 'slug' => 'youth-tours', 'logo_key' => 'docents', 'collects_visitor_count' => true]);
    $flagsOff = array_fill_keys([
        'has_meetings', 'has_documents', 'has_scheduling', 'has_vetting', 'has_announcements', 'has_bookings',
        'collects_visitor_count', 'collects_extra_interactions', 'collects_visitor_provenance', 'visitor_figures_await_booking',
    ], false);

    $this->actingAs(Member::factory()->superTier()->create())
        ->patch('/officer/settings/groups/youth-tours', structurePayload($root, [
            ...$flagsOff, 'logo_key' => null, 'time_boxed' => false, 'start_date' => '2026-01-01', 'end_date' => null,
        ]))
        ->assertSessionHasNoErrors();

    $group->refresh();
    expect($group->logo_key)->toBeNull()
        ->and($group->time_boxed)->toBeFalse()
        ->and($group->start_date)->toBeNull()
        ->and($group->end_date)->toBeNull()
        ->and($group->has_meetings)->toBeFalse()
        ->and($group->has_scheduling)->toBeFalse()
        ->and($group->collects_visitor_count)->toBeFalse();
});

it('never changes the slug', function () {
    $root = structureRoot();
    $group = Group::factory()->program()->create(['parent_id' => $root->id, 'slug' => 'youth-tours']);

    $this->actingAs(Member::factory()->superTier()->create())
        ->patch('/officer/settings/groups/youth-tours', structurePayload($root, ['slug' => 'crafted-slug']))
        ->assertSessionHasNoErrors();

    expect($group->fresh()->slug)->toBe('youth-tours')
        ->and(Group::where('slug', 'crafted-slug')->exists())->toBeFalse();
});

it('refuses a parent that is the Group itself or one of its own Groups', function (string $which) {
    $root = structureRoot();
    $group = Group::factory()->program()->create(['parent_id' => $root->id, 'slug' => 'docents']);
    $child = Group::factory()->cohort()->create(['parent_id' => $group->id]);
    $grandchild = Group::factory()->workingGroup()->create(['parent_id' => $child->id]);
    $parent = ['self' => $group, 'child' => $child, 'grandchild' => $grandchild][$which];

    $this->actingAs(Member::factory()->superTier()->create())
        ->patch('/officer/settings/groups/docents', structurePayload($parent))
        ->assertSessionHasErrors(['parent_id' => 'A Group cannot sit under itself or one of its own Groups.']);

    expect($group->fresh()->parent_id)->toBe($root->id);
})->with(['self', 'child', 'grandchild']);

it('refuses an archived or expired parent', function (string $state) {
    $root = structureRoot();
    $group = Group::factory()->program()->create(['parent_id' => $root->id, 'slug' => 'youth-tours']);
    $parent = $state === 'archived'
        ? Group::factory()->program()->archived()->create()
        : Group::factory()->program()->timeBoxed()->create(['end_date' => now()->subDay()->toDateString()]);

    $this->actingAs(Member::factory()->superTier()->create())
        ->patch('/officer/settings/groups/youth-tours', structurePayload($parent))
        ->assertSessionHasErrors(['parent_id' => 'Choose an active Group.']);

    expect($group->fresh()->parent_id)->toBe($root->id);
})->with(['archived', 'expired']);

it('keeps an archived or expired parent the Group already has', function (string $state) {
    $root = structureRoot();
    $parent = $state === 'archived'
        ? Group::factory()->program()->archived()->create(['parent_id' => $root->id])
        : Group::factory()->program()->timeBoxed()->create(['parent_id' => $root->id, 'end_date' => now()->subDay()->toDateString()]);
    Group::factory()->cohort()->create(['parent_id' => $parent->id, 'slug' => 'class-2026']);

    $this->actingAs(Member::factory()->superTier()->create())
        ->patch('/officer/settings/groups/class-2026', structurePayload($parent))
        ->assertSessionHasNoErrors();

    expect(Group::where('slug', 'class-2026')->sole()->name)->toBe('Youth Tours Renamed');
})->with(['archived', 'expired']);

it('offers an archived current parent, marked inactive, among the active parents', function () {
    $root = structureRoot();
    $parent = Group::factory()->program()->archived()->create(['parent_id' => $root->id, 'name' => 'Old Tours']);
    Group::factory()->cohort()->create(['parent_id' => $parent->id, 'slug' => 'class-2026']);

    $this->actingAs(Member::factory()->superTier()->create())
        ->get('/officer/settings/groups/class-2026')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('parents', [
                ['id' => $root->id, 'name' => 'DMV', 'depth' => 0, 'inactive' => false],
                ['id' => $parent->id, 'name' => 'Old Tours', 'depth' => 1, 'inactive' => true],
            ]));
});

it('requires both dates when time-boxed, the end on or after the start', function () {
    $root = structureRoot();
    Group::factory()->program()->create(['parent_id' => $root->id, 'slug' => 'youth-tours']);
    $actor = Member::factory()->superTier()->create();

    $this->actingAs($actor)
        ->patch('/officer/settings/groups/youth-tours', structurePayload($root, ['start_date' => null, 'end_date' => null]))
        ->assertSessionHasErrors(['start_date', 'end_date']);

    $this->actingAs($actor)
        ->patch('/officer/settings/groups/youth-tours', structurePayload($root, ['start_date' => '2027-01-02', 'end_date' => '2027-01-01']))
        ->assertSessionHasErrors(['end_date' => 'The end date must be on or after the start date.']);
});

it('refuses unknown kinds, visibilities and logos, a blank name, and a negative order or multiplier', function () {
    $root = structureRoot();
    Group::factory()->program()->create(['parent_id' => $root->id, 'slug' => 'youth-tours']);

    $this->actingAs(Member::factory()->superTier()->create())
        ->patch('/officer/settings/groups/youth-tours', structurePayload($root, [
            'kind' => 'committee', 'listing_visibility' => 'secret', 'logo_key' => 'unknown',
            'display_order' => -1, 'hours_multiplier' => -1, 'name' => '',
        ]))
        ->assertSessionHasErrors(['kind', 'listing_visibility', 'logo_key', 'display_order', 'hours_multiplier', 'name']);
});

it('refuses to save the root or a Container', function () {
    $root = structureRoot();
    Group::factory()->container()->create(['parent_id' => $root->id, 'slug' => 'programs', 'name' => 'Programs']);
    $actor = Member::factory()->superTier()->create();
    $error = ['group' => 'The root and Containers have no settings here.'];

    $this->actingAs($actor)
        ->patch('/officer/settings/groups/programs', structurePayload($root, ['kind' => 'container']))
        ->assertSessionHasErrors($error);
    $this->actingAs($actor)
        ->patch('/officer/settings/groups/dmv', structurePayload($root, ['parent_id' => null]))
        ->assertSessionHasErrors($error);

    expect(Group::where('slug', 'programs')->sole()->name)->toBe('Programs')
        ->and($root->fresh()->name)->toBe('DMV');
});

it('lands on the tree when the Group becomes a Container', function () {
    $root = structureRoot();
    $group = Group::factory()->standingCommittee()->create(['parent_id' => $root->id, 'slug' => 'special-projects']);

    $this->actingAs(Member::factory()->superTier()->create())
        ->from('/officer/settings/groups/special-projects')
        ->patch('/officer/settings/groups/special-projects', structurePayload($root, ['kind' => 'container']))
        ->assertSessionHasNoErrors()
        ->assertRedirect('/officer/settings');

    expect($group->fresh()->kind->value)->toBe('container');
});

it('refuses to save an archived Group until it is unarchived', function () {
    $root = structureRoot();
    $group = Group::factory()->program()->archived()->create(['parent_id' => $root->id, 'slug' => 'old-tours', 'name' => 'Old Tours']);

    $this->actingAs(Member::factory()->superTier()->create())
        ->patch('/officer/settings/groups/old-tours', structurePayload($root))
        ->assertSessionHasErrors(['group' => 'Unarchive this Group to change its settings.']);

    expect($group->fresh()->name)->toBe('Old Tours');
});

it('saves an expired time-boxed Group, so new dates revive it', function () {
    $root = structureRoot();
    $group = Group::factory()->cohort()->timeBoxed()->create(['parent_id' => $root->id, 'slug' => 'class-2024', 'end_date' => '2025-06-30']);

    $this->actingAs(Member::factory()->superTier()->create())
        ->patch('/officer/settings/groups/class-2024', structurePayload($root, ['start_date' => '2024-09-01', 'end_date' => now()->addYear()->toDateString()]))
        ->assertSessionHasNoErrors();

    expect($group->fresh()->isActive())->toBeTrue();
});

it('refuses to make a stewardship holder a Container, naming the stewardship', function () {
    $root = structureRoot();
    $records = Group::factory()->standingCommittee()->create(['parent_id' => $root->id, 'slug' => 'records', 'name' => 'Records']);
    $records->stewardships()->create(['function' => StewardshipFunction::MemberAdmin]);

    $this->actingAs(Member::factory()->superTier()->create())
        ->patch('/officer/settings/groups/records', structurePayload($root, ['kind' => 'container']))
        ->assertSessionHasErrors(['kind' => 'Records holds Member administration. Move the Stewardship first.']);

    expect($records->fresh()->kind->value)->toBe('standing_committee');
});

it('refuses to end a stewardship holder before today, naming the stewardship', function () {
    $root = structureRoot();
    $records = Group::factory()->standingCommittee()->create(['parent_id' => $root->id, 'slug' => 'records', 'name' => 'Records']);
    $records->stewardships()->create(['function' => StewardshipFunction::OrgMail]);

    $this->actingAs(Member::factory()->superTier()->create())
        ->patch('/officer/settings/groups/records', structurePayload($root, [
            'start_date' => '2024-09-01', 'end_date' => now()->subDay()->toDateString(),
        ]))
        ->assertSessionHasErrors(['end_date' => 'Records holds Org-wide sender. Move the Stewardship first.']);

    expect($records->fresh()->time_boxed)->toBeFalse();
});

it('lets a stewardship holder be time-boxed while its end date is today or later', function () {
    $root = structureRoot();
    $records = Group::factory()->standingCommittee()->create(['parent_id' => $root->id, 'slug' => 'records', 'name' => 'Records']);
    $records->stewardships()->create(['function' => StewardshipFunction::OrgMail]);

    $this->actingAs(Member::factory()->superTier()->create())
        ->patch('/officer/settings/groups/records', structurePayload($root, [
            'start_date' => '2024-09-01', 'end_date' => now()->toDateString(),
        ]))
        ->assertSessionHasNoErrors();

    expect($records->fresh()->isActive())->toBeTrue();
});

it('forbids saving to anyone below the super-tier', function (Role|string $authority) {
    $root = structureRoot();
    $group = Group::factory()->program()->create(['parent_id' => $root->id, 'slug' => 'youth-tours', 'name' => 'Youth Tours']);

    $this->actingAs(structureActor($authority))
        ->patch('/officer/settings/groups/youth-tours', structurePayload($root))
        ->assertForbidden();

    expect($group->fresh()->name)->toBe('Youth Tours');
})->with(['plain', 'records', Role::Chair]);

it('forbids the Group\'s own Chair', function () {
    $root = structureRoot();
    $group = Group::factory()->program()->create(['parent_id' => $root->id, 'slug' => 'youth-tours', 'name' => 'Youth Tours']);
    $chair = Member::factory()->create();
    $membership = GroupMember::factory()->create(['group_id' => $group->id, 'member_id' => $chair->id]);
    GroupMemberRole::factory()->role(Role::Chair)->create(['group_member_id' => $membership->id]);

    $this->actingAs($chair)
        ->patch('/officer/settings/groups/youth-tours', structurePayload($root))
        ->assertForbidden();
});

// --- Last changed ---------------------------------------------------------------------

it('records the last editor and shows who and when under the form', function () {
    $root = structureRoot();
    $group = Group::factory()->program()->create(['parent_id' => $root->id, 'slug' => 'youth-tours']);
    $actor = Member::factory()->superTier()->create(['first_name' => 'Ada', 'last_name' => 'Lovelace']);

    $this->travelTo('2026-10-24 15:00:00');
    $this->actingAs($actor)
        ->patch('/officer/settings/groups/youth-tours', structurePayload($root))
        ->assertSessionHasNoErrors();

    expect($group->fresh()->updatedBy->is($actor))->toBeTrue();

    $this->actingAs($actor)
        ->get('/officer/settings/groups/youth-tours')
        ->assertInertia(fn (Assert $page) => $page
            ->where('lastChanged', ['name' => 'Ada Lovelace', 'date' => '2026-10-24']));
});

// --- The rail follows -----------------------------------------------------------------

it('moves the Group on the rail once its parent changes', function () {
    $root = structureRoot();
    $programs = Group::factory()->container()->publicListing()->create(['parent_id' => $root->id, 'slug' => 'programs', 'display_order' => 1]);
    $friends = Group::factory()->container()->publicListing()->create(['parent_id' => $root->id, 'slug' => 'friends', 'display_order' => 2]);
    Group::factory()->program()->publicListing()->create(['parent_id' => $programs->id, 'slug' => 'youth-tours', 'name' => 'Youth Tours']);
    $actor = Member::factory()->superTier()->create();
    $unboxed = ['listing_visibility' => 'public', 'time_boxed' => false, 'start_date' => null, 'end_date' => null];

    $this->actingAs($actor)->get('/dashboard')
        ->assertInertia(fn (Assert $page) => $page
            ->where('rail.otherGroups.items.0.children.0.groupId', 'youth-tours')
            ->missing('rail.otherGroups.items.1.children'));

    $this->actingAs($actor)
        ->patch('/officer/settings/groups/youth-tours', structurePayload($friends, $unboxed))
        ->assertSessionHasNoErrors();

    $this->actingAs($actor)->get('/dashboard')
        ->assertInertia(fn (Assert $page) => $page
            ->missing('rail.otherGroups.items.0.children')
            ->where('rail.otherGroups.items.1.children.0.groupId', 'youth-tours'));
});

it('drops a peer\'s link on the rail once it becomes a Container', function () {
    $root = structureRoot();
    Group::factory()->standingCommittee()->publicListing()->create(['parent_id' => $root->id, 'slug' => 'special-projects', 'name' => 'Special Projects']);
    $actor = Member::factory()->superTier()->create();

    $this->actingAs($actor)->get('/dashboard')
        ->assertInertia(fn (Assert $page) => $page->where('rail.otherGroups.items.0.href', '/groups/special-projects'));

    $this->actingAs($actor)
        ->patch('/officer/settings/groups/special-projects', structurePayload($root, [
            'kind' => 'container', 'listing_visibility' => 'public', 'time_boxed' => false, 'start_date' => null, 'end_date' => null,
        ]))
        ->assertSessionHasNoErrors();

    $this->actingAs($actor)->get('/dashboard')
        ->assertInertia(fn (Assert $page) => $page->missing('rail.otherGroups.items.0.href'));
});
