<?php

use App\Enums\LifecycleState;
use App\Enums\ListingVisibility;
use App\Enums\Role;
use App\Enums\StewardshipFunction;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\GroupMemberRole;
use App\Models\Member;
use Database\Seeders\OrgTreeSeeder;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * Create a Group from DMV Settings (#842, PRD #836, ADR-0036 §4 and §8): the super-tier names
 * a Group, its slug, its parent and its kind; everything else starts at a default. The
 * browser lands on the new Group's structure form.
 *
 * Asserted from outside: the HTTP response, the session errors and the stored Group.
 */

/** The DMV root, inline, as the tree has it. */
function createGroupRoot(): Group
{
    return Group::factory()->standingCommittee()->publicListing()->create(['slug' => 'dmv', 'name' => 'DMV', 'display_order' => 0]);
}

/** A section under the root (#850): the Programs Container, where a new Group goes. */
function createGroupSection(): Group
{
    return Group::factory()->container()->create(['parent_id' => createGroupRoot()->id, 'slug' => 'programs', 'name' => 'Programs', 'display_order' => 1]);
}

/** A plain Member, a Records Member, or a Member holding $role in some Group. */
function createGroupActor(Role|string $authority): Member
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

it('creates a Group and lands on its structure form', function () {
    $section = createGroupSection();

    $this->actingAs(Member::factory()->superTier()->create())
        ->post('/officer/settings/groups', [
            'name' => 'Youth Tours',
            'slug' => 'youth-tours',
            'parent_id' => $section->id,
            'kind' => 'program',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect('/officer/settings/groups/youth-tours');

    $group = Group::where('slug', 'youth-tours')->sole();
    expect($group->name)->toBe('Youth Tours')
        ->and($group->parent_id)->toBe($section->id)
        ->and($group->kind->value)->toBe('program');
});

it('lands on the French structure form from French', function () {
    $section = createGroupSection();
    $actor = Member::factory()->superTier()->create();

    $this->withLocaleRoutes('fr', function () use ($actor, $section) {
        $this->actingAs($actor)
            ->post('/officer/settings/groups', [
                'name' => 'Visites jeunesse', 'slug' => 'visites-jeunesse', 'parent_id' => $section->id, 'kind' => 'program',
            ])
            ->assertRedirect('/fr/officier/parametres/groupes/visites-jeunesse');
    });
});

it('offers every active Group under a section as a parent, in tree order, the root left out', function () {
    $root = createGroupRoot();
    $programs = Group::factory()->container()->create(['parent_id' => $root->id, 'name' => 'Programs', 'display_order' => 2]);
    $governance = Group::factory()->container()->create(['parent_id' => $root->id, 'name' => 'Governance', 'display_order' => 1]);
    $docents = Group::factory()->program()->create(['parent_id' => $programs->id, 'name' => 'Docents']);
    $cohort = Group::factory()->cohort()->create(['parent_id' => $docents->id, 'name' => 'Class of 2026']);
    Group::factory()->program()->archived()->create(['parent_id' => $programs->id, 'name' => 'Youth Tours']);
    Group::factory()->cohort()->timeBoxed()->create(['parent_id' => $docents->id, 'name' => 'Class of 2024', 'end_date' => now()->subDay()->toDateString()]);
    // A real Group straight under the root (Special Projects, PRD #289) is a section like a Container.
    $special = Group::factory()->standingCommittee()->create(['parent_id' => $root->id, 'name' => 'Special Projects', 'display_order' => 3]);
    $exhibit = Group::factory()->project()->create(['parent_id' => $special->id, 'name' => 'Exhibit']);
    // An archived root child is no section: neither it nor its Groups are offered.
    $old = Group::factory()->container()->archived()->create(['parent_id' => $root->id, 'name' => 'Old Section', 'display_order' => 4]);
    Group::factory()->program()->create(['parent_id' => $old->id, 'name' => 'Old Program']);

    $this->actingAs(Member::factory()->superTier()->create())
        ->get('/officer/settings')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            // Depth counts from the section (0); each option names its section.
            ->where('parentOptions', [
                ['id' => $governance->id, 'name' => 'Governance', 'depth' => 0, 'sectionId' => $governance->id],
                ['id' => $programs->id, 'name' => 'Programs', 'depth' => 0, 'sectionId' => $programs->id],
                ['id' => $docents->id, 'name' => 'Docents', 'depth' => 1, 'sectionId' => $programs->id],
                ['id' => $cohort->id, 'name' => 'Class of 2026', 'depth' => 2, 'sectionId' => $programs->id],
                ['id' => $special->id, 'name' => 'Special Projects', 'depth' => 0, 'sectionId' => $special->id],
                ['id' => $exhibit->id, 'name' => 'Exhibit', 'depth' => 1, 'sectionId' => $special->id],
            ]));
});

it('sends the kinds the Create dialog offers, Container left out (seeder only)', function () {
    createGroupRoot();

    $this->actingAs(Member::factory()->superTier()->create())
        ->get('/officer/settings')
        ->assertInertia(fn (Assert $page) => $page
            ->where('kinds', ['standing_committee', 'program', 'working_group', 'project', 'cohort']));
});

it('starts the new Group at every default', function () {
    $section = createGroupSection();

    $this->actingAs(Member::factory()->superTier()->create())->post('/officer/settings/groups', [
        'name' => 'Youth Tours', 'slug' => 'youth-tours', 'parent_id' => $section->id, 'kind' => 'program',
    ])->assertSessionHasNoErrors();

    $group = Group::where('slug', 'youth-tours')->sole();
    expect($group->isActive())->toBeTrue()
        ->and($group->lifecycle_state)->toBe(LifecycleState::Active)
        ->and($group->listing_visibility)->toBe(ListingVisibility::Public)
        ->and($group->display_order)->toBe(0)
        ->and($group->logo_key)->toBeNull()
        ->and($group->time_boxed)->toBeFalse()
        ->and($group->start_date)->toBeNull()
        ->and($group->end_date)->toBeNull()
        ->and($group->has_meetings)->toBeFalse()
        ->and($group->has_documents)->toBeFalse()
        ->and($group->has_scheduling)->toBeFalse()
        ->and($group->has_vetting)->toBeFalse()
        ->and($group->has_announcements)->toBeFalse()
        ->and($group->has_bookings)->toBeFalse()
        ->and($group->collects_visitor_count)->toBeFalse()
        ->and($group->collects_extra_interactions)->toBeFalse()
        ->and($group->collects_visitor_provenance)->toBeFalse()
        ->and($group->visitor_figures_await_booking)->toBeFalse()
        ->and($group->hours_multiplier)->toBe('1.000');
});

it('refuses a slug another Group uses, on the slug field', function () {
    $section = createGroupSection();
    Group::factory()->create(['slug' => 'youth-tours']);

    $this->actingAs(Member::factory()->superTier()->create())
        ->from('/officer/settings')
        ->post('/officer/settings/groups', [
            'name' => 'Youth Tours', 'slug' => 'youth-tours', 'parent_id' => $section->id, 'kind' => 'program',
        ])
        ->assertRedirect('/officer/settings')
        ->assertSessionHasErrors(['slug' => 'Another Group uses this slug.']);

    expect(Group::where('slug', 'youth-tours')->count())->toBe(1);
});

it('refuses a slug that is not URL-safe', function (string $slug) {
    $section = createGroupSection();

    $this->actingAs(Member::factory()->superTier()->create())
        ->post('/officer/settings/groups', [
            'name' => 'Youth Tours', 'slug' => $slug, 'parent_id' => $section->id, 'kind' => 'program',
        ])
        ->assertSessionHasErrors(['slug' => 'Use lowercase letters, digits and hyphens.']);
})->with(['Youth-Tours', 'youth tours', '-youth', 'youth--tours', 'jeunes-visités']);

it('refuses an archived or expired parent', function (string $state) {
    createGroupRoot();
    $parent = $state === 'archived'
        ? Group::factory()->program()->archived()->create()
        : Group::factory()->program()->timeBoxed()->create(['end_date' => now()->subDay()->toDateString()]);

    $this->actingAs(Member::factory()->superTier()->create())
        ->post('/officer/settings/groups', [
            'name' => 'Youth Tours', 'slug' => 'youth-tours', 'parent_id' => $parent->id, 'kind' => 'program',
        ])
        ->assertSessionHasErrors(['parent_id' => 'Choose an active Group.']);

    expect(Group::where('slug', 'youth-tours')->exists())->toBeFalse();
})->with(['archived', 'expired']);

it('refuses the root as a parent, even on a crafted request', function () {
    $root = createGroupRoot();

    $this->actingAs(Member::factory()->superTier()->create())
        ->post('/officer/settings/groups', [
            'name' => 'Youth Tours', 'slug' => 'youth-tours', 'parent_id' => $root->id, 'kind' => 'program',
        ])
        ->assertSessionHasErrors(['parent_id' => 'Choose a section, not the root.']);

    expect(Group::where('slug', 'youth-tours')->exists())->toBeFalse();
});

it('refuses to create a Container', function () {
    $section = createGroupSection();

    $this->actingAs(Member::factory()->superTier()->create())
        ->post('/officer/settings/groups', [
            'name' => 'New Section', 'slug' => 'new-section', 'parent_id' => $section->id, 'kind' => 'container',
        ])
        ->assertSessionHasErrors('kind');

    expect(Group::where('slug', 'new-section')->exists())->toBeFalse();
});

it('requires every field and a known kind', function () {
    $this->actingAs(Member::factory()->superTier()->create())
        ->post('/officer/settings/groups', ['kind' => 'committee'])
        ->assertSessionHasErrors(['name', 'slug', 'parent_id', 'kind']);
});

it('forbids anyone below the super-tier', function (Role|string $authority) {
    $section = createGroupSection();

    $this->actingAs(createGroupActor($authority))
        ->post('/officer/settings/groups', [
            'name' => 'Youth Tours', 'slug' => 'youth-tours', 'parent_id' => $section->id, 'kind' => 'program',
        ])
        ->assertForbidden();

    expect(Group::where('slug', 'youth-tours')->exists())->toBeFalse();
})->with(['plain', 'records']);

// A Chair is a parent officer (#884): their create is the scoped one, and a parent outside
// their subtree is a field error, as on the structure form.
it('refuses a Chair a parent outside their subtree', function () {
    $section = createGroupSection();

    $this->actingAs(createGroupActor(Role::Chair))
        ->post('/officer/settings/groups', [
            'name' => 'Youth Tours', 'slug' => 'youth-tours', 'parent_id' => $section->id, 'kind' => 'program',
        ])
        ->assertSessionHasErrors(['parent_id' => __('dmv_settings.error.outside_subtree')]);

    expect(Group::where('slug', 'youth-tours')->exists())->toBeFalse();
});

it('records the acting Member as creator and last editor', function () {
    $section = createGroupSection();
    $actor = Member::factory()->superTier()->create();

    $this->actingAs($actor)->post('/officer/settings/groups', [
        'name' => 'Youth Tours', 'slug' => 'youth-tours', 'parent_id' => $section->id, 'kind' => 'program',
    ])->assertSessionHasNoErrors();

    $group = Group::where('slug', 'youth-tours')->sole();
    expect($group->createdBy->is($actor))->toBeTrue()
        ->and($group->updatedBy->is($actor))->toBeTrue();
});

it('leaves seeded Groups without an author', function () {
    $this->seed(OrgTreeSeeder::class);

    expect(Group::count())->toBeGreaterThan(0)
        ->and(Group::whereNotNull('created_by')->orWhereNotNull('updated_by')->exists())->toBeFalse();
});
