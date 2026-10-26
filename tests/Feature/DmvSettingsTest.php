<?php

use App\Enums\Role;
use App\Enums\StewardshipFunction;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\GroupMemberRole;
use App\Models\Member;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * DMV Settings (#841, PRD #836, ADR-0036 §3): the super-tier's page for the org's Groups and
 * Stewardships. The Groups section lists the whole tree; every other actor gets 403 and never
 * sees the rail item.
 *
 * Asserted from outside: the HTTP response, the Inertia props and the shared rail prop.
 */

/** A Member of a Group, holding an optional role, or a Records Member when $authority is 'records'. */
function dmvSettingsActor(Role|string $authority): Member
{
    if ($authority === 'plain') {
        return Member::factory()->create();
    }

    $group = Group::factory()->program()->create();
    $member = Member::factory()->create();
    $membership = GroupMember::factory()->create(['group_id' => $group->id, 'member_id' => $member->id]);

    if ($authority === 'records') {
        $group->stewardships()->create(['function' => StewardshipFunction::MemberAdmin]);
    } else {
        GroupMemberRole::factory()->role($authority)->create(['group_member_id' => $membership->id]);
    }

    return $member;
}

// --- Access ---------------------------------------------------------------------------

it('sends a guest to the login page', function () {
    $this->get('/officer/settings')->assertRedirect('/login');
});

it('shows the Groups section to the super-tier', function () {
    $this->actingAs(Member::factory()->superTier()->create())
        ->get('/officer/settings')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('officer/settings/Groups'));
});

it('forbids a plain Member', function () {
    $this->actingAs(Member::factory()->create())
        ->get('/officer/settings')
        ->assertForbidden();
});

it('forbids a Records Member and a Group Chair', function (Role|string $authority) {
    $this->actingAs(dmvSettingsActor($authority))
        ->get('/officer/settings')
        ->assertForbidden();
})->with(['records', Role::Chair]);

it('resolves the French twins under /fr/', function () {
    $member = Member::factory()->superTier()->create();

    $this->withLocaleRoutes('fr', function () use ($member) {
        $this->actingAs($member)
            ->get('/fr/officier/parametres')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('officer/settings/Groups')->where('locale', 'fr'));

        $this->actingAs($member)
            ->get('/fr/officier/parametres/intendances')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('officer/settings/Stewardships')->where('locale', 'fr'));
    });
});

it('shows the Stewardships section to the super-tier only', function () {
    $this->actingAs(Member::factory()->superTier()->create())
        ->get('/officer/settings/stewardships')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('officer/settings/Stewardships'));

    $this->actingAs(dmvSettingsActor('records'))
        ->get('/officer/settings/stewardships')
        ->assertForbidden();
});

it('opens a Group structure page for the super-tier only', function () {
    Group::factory()->program()->create(['slug' => 'youth-tours', 'name' => 'Youth Tours']);

    $this->actingAs(Member::factory()->superTier()->create())
        ->get('/officer/settings/groups/youth-tours')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('officer/settings/Group')
            ->where('group.slug', 'youth-tours')
            ->where('group.name', 'Youth Tours'));

    $this->actingAs(dmvSettingsActor(Role::Chair))
        ->get('/officer/settings/groups/youth-tours')
        ->assertForbidden();
});

// --- Rail -----------------------------------------------------------------------------

it('hides the DMV Settings rail item from everyone but the super-tier', function (Role|string $authority) {
    $this->actingAs(dmvSettingsActor($authority))
        ->get('/dashboard')
        ->assertOk()
        // A plain Member or a Chair gets no Officer Tools at all; a Records Member gets Members only.
        ->assertInertia(fn (Assert $page) => $page->where(
            'rail',
            fn ($rail) => collect(data_get($rail, 'officer.items', []))->doesntContain('key', 'settings'),
        ));
})->with(['plain' => 'plain', 'records' => 'records', 'chair' => Role::Chair]);

// --- Groups tree ----------------------------------------------------------------------

it('lists every Group under its parent in display order with kind, archived marker and flags', function () {
    $root = Group::factory()->standingCommittee()->publicListing()->create(['slug' => 'dmv', 'name' => 'DMV', 'display_order' => 0]);
    $programs = Group::factory()->container()->create(['parent_id' => $root->id, 'slug' => 'programs', 'name' => 'Programs', 'display_order' => 2]);
    $governance = Group::factory()->container()->create(['parent_id' => $root->id, 'slug' => 'governance', 'name' => 'Governance', 'display_order' => 1]);
    $records = Group::factory()->standingCommittee()->create(['parent_id' => $governance->id, 'slug' => 'records', 'name' => 'Records']);
    // Same display_order as Docents: name breaks the tie.
    Group::factory()->program()->archived()->create(['parent_id' => $programs->id, 'slug' => 'youth-tours', 'name' => 'Youth Tours']);
    $docents = Group::factory()->program()->create(['parent_id' => $programs->id, 'slug' => 'docents', 'name' => 'Docents']);
    Group::factory()->program()->timeBoxed()->create([
        'parent_id' => $docents->id, 'slug' => 'docents-2024', 'name' => 'Docent Class of 2024',
        'start_date' => '2024-09-01', 'end_date' => '2025-06-30',
    ]);

    $this->actingAs(Member::factory()->superTier()->create())
        ->get('/officer/settings')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('officer/settings/Groups')
            ->count('tree', 7)
            ->where('tree.0', [
                'id' => $root->id, 'slug' => 'dmv', 'name' => 'DMV', 'depth' => 0, 'kind' => 'standing_committee',
                'archived' => false, 'endedOn' => null, 'editable' => false, 'href' => null,
                'capabilities' => [...array_fill_keys(Group::CAPABILITY_FLAGS, false), 'has_meetings' => true, 'has_documents' => true],
                'stewardships' => [], 'hoursMultiplier' => 1,
            ])
            ->where('tree.1.slug', 'governance')
            ->where('tree.1.depth', 1)
            ->where('tree.1.kind', 'container')
            ->where('tree.1.editable', false)
            ->where('tree.1.href', null)
            ->where('tree.2', [
                'id' => $records->id, 'slug' => 'records', 'name' => 'Records', 'depth' => 2,
                'kind' => 'standing_committee', 'archived' => false, 'endedOn' => null, 'editable' => true,
                'href' => '/officer/settings/groups/records',
                'capabilities' => [...array_fill_keys(Group::CAPABILITY_FLAGS, false), 'has_meetings' => true, 'has_documents' => true],
                'stewardships' => [], 'hoursMultiplier' => 1,
            ])
            ->where('tree.3.slug', 'programs')
            ->where('tree.4.slug', 'docents')
            ->where('tree.4.kind', 'program')
            ->where('tree.5.slug', 'docents-2024')
            ->where('tree.5.depth', 3)
            ->where('tree.5.endedOn', '2025-06-30')
            ->where('tree.5.archived', false)
            ->where('tree.6.slug', 'youth-tours')
            ->where('tree.6.archived', true)
            ->where('tree.6.editable', true)
            ->where('tree.6.href', '/officer/settings/groups/youth-tours'));
});

it('carries each row\'s capabilities, stewardships and hours multiplier (#848)', function () {
    $root = Group::factory()->standingCommittee()->create(['slug' => 'dmv', 'name' => 'DMV']);
    $records = Group::factory()->standingCommittee()->create(['parent_id' => $root->id, 'slug' => 'records', 'name' => 'Records', 'display_order' => 1]);
    $records->stewardships()->create(['function' => StewardshipFunction::MemberAdmin]);
    $records->stewardships()->create(['function' => StewardshipFunction::OrgMail]);
    Group::factory()->program()->create([
        'parent_id' => $root->id, 'slug' => 'romwalks', 'name' => 'ROMWalks', 'display_order' => 2, 'has_bookings' => true, 'hours_multiplier' => 2,
    ]);

    $this->actingAs(Member::factory()->superTier()->create())
        ->get('/officer/settings')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('tree.1.slug', 'records')
            ->where('tree.1.stewardships', ['member_admin', 'org_mail'])
            ->where('tree.1.hoursMultiplier', 1)
            ->where('tree.2.slug', 'romwalks')
            ->where('tree.2.capabilities', [
                'has_meetings' => true, 'has_documents' => true, 'has_scheduling' => true,
                'has_vetting' => false, 'has_announcements' => false, 'has_bookings' => true,
            ])
            ->where('tree.2.stewardships', [])
            ->where('tree.2.hoursMultiplier', 2));
});

it('loads the tree in the same number of queries however many Groups there are (#848)', function () {
    $member = Member::factory()->superTier()->create();
    $root = Group::factory()->standingCommittee()->create(['slug' => 'dmv', 'name' => 'DMV']);
    $root->stewardships()->create(['function' => StewardshipFunction::OrgMail]);

    // The first request of a test pays one-off queries, so warm up before measuring.
    $this->actingAs($member)->get('/officer/settings')->assertOk();

    $queriesFor = function (int $groups) use ($member, $root): int {
        Group::factory()->count($groups)->program()->create(['parent_id' => $root->id]);
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($member)->get('/officer/settings')->assertOk();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    };

    expect($queriesFor(2))->toBe($queriesFor(10));
});

it('leaves endedOn null for a time-boxed Group still running', function () {
    Group::factory()->program()->timeBoxed()->create(['slug' => 'running']);

    $this->actingAs(Member::factory()->superTier()->create())
        ->get('/officer/settings')
        ->assertInertia(fn (Assert $page) => $page->where('tree.0.endedOn', null));
});

it('emits French structure hrefs under /fr/', function () {
    Group::factory()->program()->create(['slug' => 'docents', 'name' => 'Docents']);
    $member = Member::factory()->superTier()->create();

    $this->withLocaleRoutes('fr', function () use ($member) {
        $this->actingAs($member)
            ->get('/fr/officier/parametres')
            ->assertInertia(fn (Assert $page) => $page
                ->where('tree.0.href', '/fr/officier/parametres/groupes/docents')
                ->where('groupsHref', '/fr/officier/parametres'));
    });
});
