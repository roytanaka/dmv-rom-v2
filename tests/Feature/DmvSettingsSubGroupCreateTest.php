<?php

use App\Enums\Kind;
use App\Enums\LifecycleState;
use App\Enums\ListingVisibility;
use App\Enums\MembershipStatus;
use App\Enums\Role;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\GroupMemberRole;
use App\Models\Member;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * A parent officer creates a sub-group from DMV Settings (#884, PRD #881, ADR-0036 §11): a
 * name and a parent from their own subtree. The server makes the slug and sets the defaults
 * (a Working group with Meetings and Documents on, Group listing). The super-tier's Create
 * form is unchanged (DmvSettingsGroupCreateTest).
 *
 * Asserted from outside: the HTTP response, the session errors and the stored Group.
 */

/** Root › Programs (Container) › Docents, the Group a parent officer runs. */
function subGroupTree(): array
{
    $root = Group::factory()->standingCommittee()->publicListing()->create(['slug' => 'dmv', 'name' => 'DMV', 'display_order' => 0]);
    $section = Group::factory()->container()->create(['parent_id' => $root->id, 'slug' => 'programs', 'name' => 'Programs']);
    $docents = Group::factory()->program()->create(['parent_id' => $section->id, 'slug' => 'docents', 'name' => 'Docents']);

    return [$root, $section, $docents];
}

/** A member of $group holding $role. */
function subGroupOfficerOf(Group $group, Role $role = Role::Chair): Member
{
    $member = Member::factory()->create();
    $membership = GroupMember::factory()->create(['group_id' => $group->id, 'member_id' => $member->id]);
    GroupMemberRole::factory()->role($role)->create(['group_member_id' => $membership->id]);

    return $member;
}

it('offers the officer\'s Group and its active descendants as parents, in tree order', function () {
    [, $section, $docents] = subGroupTree();
    Group::factory()->program()->create(['parent_id' => $section->id, 'slug' => 'guides', 'name' => 'Guides']);
    $sections = Group::factory()->workingGroup()->create(['parent_id' => $docents->id, 'slug' => 'docents-sections', 'name' => 'Sections', 'display_order' => 1]);
    $east = Group::factory()->workingGroup()->create(['parent_id' => $sections->id, 'slug' => 'east-wing', 'name' => 'East Wing']);
    Group::factory()->workingGroup()->archived()->create(['parent_id' => $docents->id, 'slug' => 'old', 'name' => 'Old', 'display_order' => 2]);

    $this->actingAs(subGroupOfficerOf($docents))
        ->get('/officer/settings')
        ->assertInertia(fn (Assert $page) => $page
            ->where('subGroupParents', [
                ['id' => $docents->id, 'name' => 'Docents', 'depth' => 0],
                ['id' => $sections->id, 'name' => 'Sections', 'depth' => 1],
                ['id' => $east->id, 'name' => 'East Wing', 'depth' => 2],
            ]));
});

it('sends the super-tier no sub-group parents', function () {
    subGroupTree();

    $this->actingAs(Member::factory()->superTier()->create())
        ->get('/officer/settings')
        ->assertInertia(fn (Assert $page) => $page->where('subGroupParents', []));
});

it('lets a parent officer create a sub-group under their Group and lands on its form', function (Role $role) {
    [, , $docents] = subGroupTree();
    $officer = subGroupOfficerOf($docents, $role);

    $this->actingAs($officer)
        ->post('/officer/settings/groups', ['name' => 'Tours', 'parent_id' => $docents->id])
        ->assertSessionHasNoErrors()
        ->assertRedirect('/officer/settings/groups/tours');

    $group = Group::where('slug', 'tours')->sole();
    expect($group->name)->toBe('Tours')
        ->and($group->parent_id)->toBe($docents->id)
        ->and($group->created_by)->toBe($officer->id)
        ->and($group->updated_by)->toBe($officer->id);
})->with([Role::Chair, Role::Secretary]);

it('lets a parent officer create under a descendant of their Group, at any depth', function () {
    [, , $docents] = subGroupTree();
    $sections = Group::factory()->workingGroup()->create(['parent_id' => $docents->id, 'slug' => 'docents-sections', 'name' => 'Sections']);
    $east = Group::factory()->workingGroup()->create(['parent_id' => $sections->id, 'slug' => 'east-wing', 'name' => 'East Wing']);

    $this->actingAs(subGroupOfficerOf($docents))
        ->post('/officer/settings/groups', ['name' => 'Gallery 1', 'parent_id' => $east->id])
        ->assertSessionHasNoErrors()
        ->assertRedirect('/officer/settings/groups/gallery-1');

    expect(Group::where('slug', 'gallery-1')->sole()->parent_id)->toBe($east->id);
});

it('refuses a parent outside the officer\'s subtree, the root and a Container', function (string $target) {
    [$root, $section, $docents] = subGroupTree();
    $guides = Group::factory()->program()->create(['parent_id' => $section->id, 'slug' => 'guides', 'name' => 'Guides']);
    $guideSub = Group::factory()->workingGroup()->create(['parent_id' => $guides->id, 'slug' => 'guides-sections', 'name' => 'Sections']);
    $parent = ['root' => $root, 'container' => $section, 'other group' => $guides, 'other sub-group' => $guideSub][$target];

    // Refused as the structure form refuses a move outside the subtree: a parent_id error.
    $this->actingAs(subGroupOfficerOf($docents))
        ->post('/officer/settings/groups', ['name' => 'Tours', 'parent_id' => $parent->id])
        ->assertSessionHasErrors(['parent_id' => __('dmv_settings.error.outside_subtree')]);

    expect(Group::where('name', 'Tours')->exists())->toBeFalse();
})->with(['root', 'container', 'other group', 'other sub-group']);

it('gives a parent officer a parent_id error for a missing or unknown parent, not 403', function (array $payload, string $message) {
    [, , $docents] = subGroupTree();

    $this->actingAs(subGroupOfficerOf($docents))
        ->post('/officer/settings/groups', ['name' => 'Tours', ...$payload])
        ->assertSessionHasErrors(['parent_id' => $message]);

    expect(Group::where('name', 'Tours')->exists())->toBeFalse();
})->with([
    'missing' => [[], 'The parent id field is required.'],
    'unknown' => [['parent_id' => 999999], 'Choose one of your Groups or a Group below it.'],
]);

it('refuses a parent officer of an archived Group the create', function () {
    [, $section, $docents] = subGroupTree();
    $retired = Group::factory()->program()->archived()->create(['parent_id' => $section->id, 'slug' => 'retired', 'name' => 'Retired']);
    $officer = subGroupOfficerOf($retired);
    $sub = Group::factory()->workingGroup()->create(['parent_id' => $retired->id, 'slug' => 'retired-sub', 'name' => 'Retired Sub']);

    $this->actingAs($officer)
        ->post('/officer/settings/groups', ['name' => 'Tours', 'parent_id' => $sub->id])
        ->assertForbidden();

    expect(Group::where('name', 'Tours')->exists())->toBeFalse();
});

it('refuses a plain Member and a Librarian of the parent', function (string $who) {
    [, , $docents] = subGroupTree();
    $member = $who === 'plain' ? Member::factory()->create() : subGroupOfficerOf($docents, Role::Librarian);

    $this->actingAs($member)
        ->post('/officer/settings/groups', ['name' => 'Tours', 'parent_id' => $docents->id])
        ->assertForbidden();
})->with(['plain', 'librarian']);

it('refuses an archived sub-group as the parent', function () {
    [, , $docents] = subGroupTree();
    $old = Group::factory()->workingGroup()->archived()->create(['parent_id' => $docents->id, 'slug' => 'old', 'name' => 'Old']);

    $this->actingAs(subGroupOfficerOf($docents))
        ->post('/officer/settings/groups', ['name' => 'Tours', 'parent_id' => $old->id])
        ->assertSessionHasErrors(['parent_id' => 'Choose an active Group.']);

    expect(Group::where('name', 'Tours')->exists())->toBeFalse();
});

it('makes the slug from the name, then puts the parent\'s slug in front, then counts up', function () {
    [, $section, $docents] = subGroupTree();
    $guides = Group::factory()->program()->create(['parent_id' => $section->id, 'slug' => 'guides', 'name' => 'Guides']);
    Group::factory()->workingGroup()->create(['parent_id' => $guides->id, 'slug' => 'sections', 'name' => 'Sections']);
    Group::factory()->create(['slug' => 'docents-tours']);
    Group::factory()->create(['slug' => 'tours']);
    $officer = subGroupOfficerOf($docents);

    $post = fn (string $name) => $this->actingAs($officer)
        ->post('/officer/settings/groups', ['name' => $name, 'parent_id' => $docents->id])
        ->assertSessionHasNoErrors();

    $post('Café Réunion')->assertRedirect('/officer/settings/groups/cafe-reunion');
    $post('Sections')->assertRedirect('/officer/settings/groups/docents-sections');
    $post('Tours')->assertRedirect('/officer/settings/groups/docents-tours-2');

    expect(Group::where('parent_id', $docents->id)->pluck('slug')->sort()->values()->all())
        ->toBe(['cafe-reunion', 'docents-sections', 'docents-tours-2']);
});

it('counts past -2 when that is taken too', function () {
    [, , $docents] = subGroupTree();
    Group::factory()->create(['slug' => 'tours']);
    Group::factory()->create(['slug' => 'docents-tours']);
    Group::factory()->create(['slug' => 'docents-tours-2']);

    $this->actingAs(subGroupOfficerOf($docents))
        ->post('/officer/settings/groups', ['name' => 'Tours', 'parent_id' => $docents->id])
        ->assertRedirect('/officer/settings/groups/docents-tours-3');
});

it('refuses a sibling with the same name, and allows the name under another parent', function () {
    [, , $docents] = subGroupTree();
    $sections = Group::factory()->workingGroup()->create(['parent_id' => $docents->id, 'slug' => 'docents-sections', 'name' => 'Sections']);
    $officer = subGroupOfficerOf($docents);

    $this->actingAs($officer)
        ->post('/officer/settings/groups', ['name' => 'Sections', 'parent_id' => $docents->id])
        ->assertSessionHasErrors(['name' => 'Another Group under this parent has this name.']);

    $this->actingAs($officer)
        ->post('/officer/settings/groups', ['name' => 'Sections', 'parent_id' => $sections->id])
        ->assertSessionHasNoErrors()
        ->assertRedirect('/officer/settings/groups/sections');

    expect(Group::where('name', 'Sections')->count())->toBe(2);
});

it('refuses a sibling name in French', function () {
    [, , $docents] = subGroupTree();
    Group::factory()->workingGroup()->create(['parent_id' => $docents->id, 'slug' => 'docents-sections', 'name' => 'Sections']);
    $officer = subGroupOfficerOf($docents);

    $this->withLocaleRoutes('fr', function () use ($officer, $docents) {
        $this->actingAs($officer)
            ->post('/officer/settings/groups', ['name' => 'Sections', 'parent_id' => $docents->id])
            ->assertSessionHasErrors(['name' => 'Un autre groupe sous ce parent porte ce nom.']);
    });
});

it('starts the sub-group at its defaults and ignores a crafted slug, kind or flag', function () {
    [, , $docents] = subGroupTree();

    $this->actingAs(subGroupOfficerOf($docents))->post('/officer/settings/groups', [
        'name' => 'Tours',
        'parent_id' => $docents->id,
        'slug' => 'my-own-slug',
        'kind' => 'standing_committee',
        'has_scheduling' => true,
        'has_announcements' => true,
        'listing_visibility' => 'public',
        'hours_multiplier' => 3,
    ])->assertSessionHasNoErrors();

    $group = Group::where('name', 'Tours')->sole();
    expect($group->slug)->toBe('tours')
        ->and($group->kind)->toBe(Kind::WorkingGroup)
        ->and($group->lifecycle_state)->toBe(LifecycleState::Active)
        ->and($group->listing_visibility)->toBe(ListingVisibility::Group)
        ->and($group->display_order)->toBe(0)
        ->and($group->logo_key)->toBeNull()
        ->and($group->time_boxed)->toBeFalse()
        ->and($group->start_date)->toBeNull()
        ->and($group->end_date)->toBeNull()
        ->and($group->has_meetings)->toBeTrue()
        ->and($group->has_documents)->toBeTrue()
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

/*
 * Chair on create (#885, ADR-0036 §11): an optional Member from the parent's active roster
 * joins the new sub-group as Full with the Chair role, in the same save.
 */

/** A member of $group in $status, holding no role. */
function subGroupRosterMember(Group $group, MembershipStatus $status = MembershipStatus::Full): Member
{
    $member = Member::factory()->create();
    GroupMember::factory()->status($status)->create(['group_id' => $group->id, 'member_id' => $member->id]);

    return $member;
}

it('makes the chosen Chair a Full member of the sub-group with the Chair role', function () {
    [, , $docents] = subGroupTree();
    $chair = subGroupRosterMember($docents);

    $this->actingAs(subGroupOfficerOf($docents))
        ->post('/officer/settings/groups', ['name' => 'Tours', 'parent_id' => $docents->id, 'chair_id' => $chair->id])
        ->assertSessionHasNoErrors()
        ->assertRedirect('/officer/settings/groups/tours');

    $memberships = Group::where('slug', 'tours')->sole()->memberships()->with('roles')->get();
    expect($memberships)->toHaveCount(1)
        ->and($memberships[0]->member_id)->toBe($chair->id)
        ->and($memberships[0]->status)->toBe(MembershipStatus::Full)
        ->and($memberships[0]->roles->pluck('role')->all())->toBe([Role::Chair]);
});

it('sends each parent\'s active roster as its Chair candidates, by surname', function () {
    [, , $docents] = subGroupTree();
    $sections = Group::factory()->workingGroup()->create(['parent_id' => $docents->id, 'slug' => 'docents-sections', 'name' => 'Sections']);
    $officer = subGroupOfficerOf($docents);
    $officer->update(['first_name' => 'Olive', 'last_name' => 'Bennett']);
    $zed = subGroupRosterMember($docents);
    $zed->update(['first_name' => 'Zoe', 'last_name' => 'Zed']);
    $ada = subGroupRosterMember($docents, MembershipStatus::Loa);
    $ada->update(['first_name' => 'Ada', 'last_name' => 'Abbott']);
    subGroupRosterMember($docents, MembershipStatus::Resigned);
    $sectionMember = subGroupRosterMember($sections);
    $sectionMember->update(['first_name' => 'Sam', 'last_name' => 'Sato']);

    $this->actingAs($officer)
        ->get('/officer/settings')
        ->assertInertia(fn (Assert $page) => $page
            ->where('subGroupChairs', [
                $docents->id => [
                    ['id' => $ada->id, 'first_name' => 'Ada', 'last_name' => 'Abbott'],
                    ['id' => $officer->id, 'first_name' => 'Olive', 'last_name' => 'Bennett'],
                    ['id' => $zed->id, 'first_name' => 'Zoe', 'last_name' => 'Zed'],
                ],
                $sections->id => [
                    ['id' => $sectionMember->id, 'first_name' => 'Sam', 'last_name' => 'Sato'],
                ],
            ]));
});

it('sends the super-tier no Chair candidates', function () {
    [, , $docents] = subGroupTree();
    subGroupRosterMember($docents);

    $this->actingAs(Member::factory()->superTier()->create())
        ->get('/officer/settings')
        ->assertInertia(fn (Assert $page) => $page->where('subGroupChairs', []));
});

it('creates the sub-group with an empty roster when no Chair is chosen', function (array $chair) {
    [, , $docents] = subGroupTree();

    $this->actingAs(subGroupOfficerOf($docents))
        ->post('/officer/settings/groups', ['name' => 'Tours', 'parent_id' => $docents->id, ...$chair])
        ->assertSessionHasNoErrors();

    expect(Group::where('slug', 'tours')->sole()->memberships()->exists())->toBeFalse();
})->with(['omitted' => [[]], 'null' => [['chair_id' => null]]]);

it('accepts a Chair on leave from the parent', function () {
    [, , $docents] = subGroupTree();
    $chair = subGroupRosterMember($docents, MembershipStatus::Loa);

    $this->actingAs(subGroupOfficerOf($docents))
        ->post('/officer/settings/groups', ['name' => 'Tours', 'parent_id' => $docents->id, 'chair_id' => $chair->id])
        ->assertSessionHasNoErrors();

    expect(Group::where('slug', 'tours')->sole()->memberships()->sole()->member_id)->toBe($chair->id);
});

it('refuses a Chair who is not on the parent\'s active roster', function (string $who) {
    [, $section, $docents] = subGroupTree();
    $guides = Group::factory()->program()->create(['parent_id' => $section->id, 'slug' => 'guides', 'name' => 'Guides']);
    $chair = match ($who) {
        'not a member' => Member::factory()->create(),
        'another Group\'s member' => subGroupRosterMember($guides),
        'resigned' => subGroupRosterMember($docents, MembershipStatus::Resigned),
        'inactive' => subGroupRosterMember($docents, MembershipStatus::Inactive),
    };

    $this->actingAs(subGroupOfficerOf($docents))
        ->post('/officer/settings/groups', ['name' => 'Tours', 'parent_id' => $docents->id, 'chair_id' => $chair->id])
        ->assertSessionHasErrors(['chair_id' => 'Choose a member of the parent Group.']);

    expect(Group::where('name', 'Tours')->exists())->toBeFalse();
})->with(['not a member', 'another Group\'s member', 'resigned', 'inactive']);

it('refuses a Chair from outside the parent in French', function () {
    [, , $docents] = subGroupTree();
    $officer = subGroupOfficerOf($docents);
    $stranger = Member::factory()->create();

    $this->withLocaleRoutes('fr', function () use ($officer, $docents, $stranger) {
        $this->actingAs($officer)
            ->post('/officer/settings/groups', ['name' => 'Tours', 'parent_id' => $docents->id, 'chair_id' => $stranger->id])
            ->assertSessionHasErrors(['chair_id' => 'Choisissez un membre du groupe parent.']);
    });
});

it('creates no Group when the Chair\'s membership cannot be written', function () {
    [, , $docents] = subGroupTree();
    $officer = subGroupOfficerOf($docents);
    $chair = subGroupRosterMember($docents);
    GroupMember::creating(fn () => throw new RuntimeException('membership write failed'));

    $this->actingAs($officer)
        ->post('/officer/settings/groups', ['name' => 'Tours', 'parent_id' => $docents->id, 'chair_id' => $chair->id])
        ->assertServerError();

    expect(Group::where('name', 'Tours')->exists())->toBeFalse();
});
