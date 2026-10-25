<?php

use App\Enums\Role;
use App\Enums\StewardshipFunction;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\GroupStewardship;
use App\Models\Member;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * DMV Settings › Stewardships (#846, PRD #836, ADR-0036 §7 and §8): the super-tier sees the
 * two live stewardships with their holders, moves them, previews who gains and loses the
 * authority before saving, and sees who last changed each one.
 *
 * Asserted from outside: the HTTP response, the Inertia props, and the authority the
 * holders' Members carry on their next request.
 */

/** The org root plus a few Groups under it; returns them keyed by slug. */
function stewardshipsOrg(): array
{
    $root = Group::factory()->standingCommittee()->publicListing()->create(['slug' => 'dmv', 'name' => 'DMV', 'display_order' => 0]);

    return [
        'root' => $root,
        'records' => Group::factory()->standingCommittee()->create(['parent_id' => $root->id, 'slug' => 'records', 'name' => 'Records', 'display_order' => 2]),
        'executive' => Group::factory()->standingCommittee()->create(['parent_id' => $root->id, 'slug' => 'executive', 'name' => 'DMV Executive', 'display_order' => 1]),
        'awards' => Group::factory()->workingGroup()->create(['parent_id' => $root->id, 'slug' => 'awards', 'name' => 'Awards', 'display_order' => 3]),
        'retired' => Group::factory()->program()->archived()->create(['parent_id' => $root->id, 'slug' => 'retired', 'name' => 'Retired', 'display_order' => 4]),
    ];
}

/** A Member of the given Group, named so a preview can be asserted by name. */
function stewardshipsMemberOf(Group $group, string $first, string $last): Member
{
    $member = Member::factory()->create(['first_name' => $first, 'last_name' => $last]);
    GroupMember::factory()->create(['group_id' => $group->id, 'member_id' => $member->id]);

    return $member;
}

function steward(Group $group, StewardshipFunction $function): GroupStewardship
{
    return GroupStewardship::factory()->stewarding($function)->create(['group_id' => $group->id]);
}

// --- Listing --------------------------------------------------------------------------

it('lists member administration and org-wide sender with their holders, never statistics or website', function () {
    $org = stewardshipsOrg();
    steward($org['records'], StewardshipFunction::MemberAdmin);
    steward($org['records'], StewardshipFunction::OrgMail);
    steward($org['executive'], StewardshipFunction::OrgMail);
    steward($org['awards'], StewardshipFunction::Statistics);

    $this->actingAs(Member::factory()->superTier()->create())
        ->get('/officer/settings/stewardships')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('officer/settings/Stewardships')
            ->has('stewardships', 2)
            ->where('stewardships.member_admin', [
                'holders' => [['id' => $org['records']->id, 'name' => 'Records']],
                'updatedBy' => null,
                'updatedAt' => null,
            ])
            ->where('stewardships.org_mail.holders', [
                ['id' => $org['executive']->id, 'name' => 'DMV Executive'],
                ['id' => $org['records']->id, 'name' => 'Records'],
            ])
            ->missing('stewardships.statistics')
            ->missing('stewardships.website')
            // Active Groups in tree order; the root and the archived Group are not choices.
            ->where('groups', [
                ['id' => $org['executive']->id, 'name' => 'DMV Executive', 'depth' => 0],
                ['id' => $org['records']->id, 'name' => 'Records', 'depth' => 0],
                ['id' => $org['awards']->id, 'name' => 'Awards', 'depth' => 0],
            ])
            ->where('preview', null));
});

// --- Member administration ------------------------------------------------------------

it('moves member administration to one other Group and records who did it', function () {
    $org = stewardshipsOrg();
    steward($org['records'], StewardshipFunction::MemberAdmin);
    $president = Member::factory()->superTier()->create(['first_name' => 'Pat', 'last_name' => 'President']);

    $this->actingAs($president)
        ->from('/officer/settings/stewardships')
        ->put('/officer/settings/stewardships/member_admin', ['group_id' => $org['awards']->id])
        ->assertRedirect('/officer/settings/stewardships')
        ->assertSessionHasNoErrors();

    expect(Group::stewardOf(StewardshipFunction::MemberAdmin)->is($org['awards']))->toBeTrue();
    $this->assertDatabaseCount('group_stewardships', 1);
    $this->assertDatabaseHas('group_stewardships', [
        'group_id' => $org['awards']->id, 'function' => 'member_admin',
        'created_by' => $president->id, 'updated_by' => $president->id,
    ]);

    $this->actingAs($president)
        ->get('/officer/settings/stewardships')
        ->assertInertia(fn (Assert $page) => $page
            ->where('stewardships.member_admin', [
                'holders' => [['id' => $org['awards']->id, 'name' => 'Awards']],
                'updatedBy' => 'Pat President',
                'updatedAt' => now()->toDateString(),
            ]));
});

it('keeps the creator and stamps the new writer when the same holder is saved again', function () {
    $org = stewardshipsOrg();
    $first = Member::factory()->superTier()->create();
    $second = Member::factory()->superTier()->create();

    $this->actingAs($first)->put('/officer/settings/stewardships/member_admin', ['group_id' => $org['records']->id]);
    $this->actingAs($second)->put('/officer/settings/stewardships/member_admin', ['group_id' => $org['records']->id]);

    $this->assertDatabaseHas('group_stewardships', [
        'group_id' => $org['records']->id, 'function' => 'member_admin',
        'created_by' => $first->id, 'updated_by' => $second->id,
    ]);
});

it('refuses to leave member administration without a holder', function (array $payload) {
    $org = stewardshipsOrg();
    steward($org['records'], StewardshipFunction::MemberAdmin);

    $this->actingAs(Member::factory()->superTier()->create())
        ->from('/officer/settings/stewardships')
        ->put('/officer/settings/stewardships/member_admin', $payload)
        ->assertRedirect('/officer/settings/stewardships')
        ->assertSessionHasErrors(['group_id' => __('dmv_settings.error.member_admin_required')]);

    expect(Group::stewardOf(StewardshipFunction::MemberAdmin)->is($org['records']))->toBeTrue();
})->with(['empty' => [['group_id' => '']], 'absent' => [[]]]);

it('refuses an inactive Group or the root as the member administration holder', function (string $slug) {
    $org = stewardshipsOrg();
    steward($org['records'], StewardshipFunction::MemberAdmin);

    $this->actingAs(Member::factory()->superTier()->create())
        ->put('/officer/settings/stewardships/member_admin', ['group_id' => $org[$slug]->id])
        ->assertSessionHasErrors(['group_id' => __('dmv_settings.error.inactive_group')]);

    expect(Group::stewardOf(StewardshipFunction::MemberAdmin)->is($org['records']))->toBeTrue();
})->with(['retired', 'root']);

// --- Org-wide sender ------------------------------------------------------------------

it('sets org-wide sender to any number of active Groups, zero included', function () {
    $org = stewardshipsOrg();
    steward($org['records'], StewardshipFunction::OrgMail);
    $president = Member::factory()->superTier()->create();

    $this->actingAs($president)
        ->put('/officer/settings/stewardships/org_mail', ['group_ids' => [$org['executive']->id, $org['awards']->id]])
        ->assertSessionHasNoErrors();

    expect(Group::stewarding(StewardshipFunction::OrgMail)->orderBy('name')->pluck('slug')->all())
        ->toBe(['awards', 'executive']);
    $this->assertDatabaseHas('group_stewardships', ['group_id' => $org['awards']->id, 'function' => 'org_mail', 'created_by' => $president->id]);

    $this->actingAs($president)
        ->put('/officer/settings/stewardships/org_mail', ['group_ids' => []])
        ->assertSessionHasNoErrors();

    expect(Group::stewarding(StewardshipFunction::OrgMail)->count())->toBe(0);
});

it('refuses an archived Group as an org-wide sender', function () {
    $org = stewardshipsOrg();

    $this->actingAs(Member::factory()->superTier()->create())
        ->put('/officer/settings/stewardships/org_mail', ['group_ids' => [$org['records']->id, $org['retired']->id]])
        ->assertSessionHasErrors(['group_ids' => __('dmv_settings.error.inactive_group')]);

    expect(Group::stewarding(StewardshipFunction::OrgMail)->count())->toBe(0);
});

it('leaves member administration alone when org-wide sender is saved', function () {
    $org = stewardshipsOrg();
    steward($org['records'], StewardshipFunction::MemberAdmin);

    $this->actingAs(Member::factory()->superTier()->create())
        ->put('/officer/settings/stewardships/org_mail', ['group_ids' => []]);

    expect(Group::stewardOf(StewardshipFunction::MemberAdmin)->is($org['records']))->toBeTrue();
});

// --- Preview --------------------------------------------------------------------------

it('previews by name who gains and who loses member administration before saving', function () {
    $org = stewardshipsOrg();
    steward($org['records'], StewardshipFunction::MemberAdmin);
    $ada = stewardshipsMemberOf($org['records'], 'Ada', 'Zed');
    $both = stewardshipsMemberOf($org['records'], 'Bo', 'Both');
    GroupMember::factory()->create(['group_id' => $org['awards']->id, 'member_id' => $both->id]);
    $cy = stewardshipsMemberOf($org['awards'], 'Cy', 'Young');
    $al = stewardshipsMemberOf($org['awards'], 'Al', 'Abbot');

    $this->actingAs(Member::factory()->superTier()->create())
        ->get('/officer/settings/stewardships?member_admin='.$org['awards']->id)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('preview.member_admin', [
                // Sorted by surname; a Member of both Groups neither gains nor loses.
                'gain' => [['id' => $al->id, 'name' => 'Al Abbot'], ['id' => $cy->id, 'name' => 'Cy Young']],
                'lose' => [['id' => $ada->id, 'name' => 'Ada Zed']],
            ])
            // No org_mail in the draft and none saved: nothing changes.
            ->where('preview.org_mail', ['gain' => [], 'lose' => []]));
});

it('previews org-wide sender changes, leaving out Chairs who send anyway', function () {
    $org = stewardshipsOrg();
    steward($org['records'], StewardshipFunction::OrgMail);
    steward($org['records'], StewardshipFunction::MemberAdmin);
    $clerk = stewardshipsMemberOf($org['records'], 'Cam', 'Clerk');
    $exec = stewardshipsMemberOf($org['executive'], 'Eve', 'Exec');
    // A Chair of an active Group is an org-wide sender whatever the stewardship says.
    $chair = stewardshipsMemberOf($org['executive'], 'Cha', 'Chair');
    GroupMember::factory()->create(['group_id' => $org['awards']->id, 'member_id' => $chair->id])
        ->roles()->create(['role' => Role::Chair]);

    $url = '/officer/settings/stewardships?member_admin='.$org['records']->id.'&org_mail[]='.$org['executive']->id;

    $this->actingAs(Member::factory()->superTier()->create())
        ->get($url)
        ->assertInertia(fn (Assert $page) => $page
            ->where('preview.org_mail', [
                'gain' => [['id' => $exec->id, 'name' => 'Eve Exec']],
                'lose' => [['id' => $clerk->id, 'name' => 'Cam Clerk']],
            ])
            ->where('preview.member_admin', ['gain' => [], 'lose' => []]));
});

it('sends no preview without a draft', function () {
    stewardshipsOrg();

    $this->actingAs(Member::factory()->superTier()->create())
        ->get('/officer/settings/stewardships')
        ->assertInertia(fn (Assert $page) => $page->where('preview', null));
});

it('refuses a draft that is not Group ids', function () {
    stewardshipsOrg();

    $this->actingAs(Member::factory()->superTier()->create())
        ->get('/officer/settings/stewardships?member_admin=records&org_mail[]=x')
        ->assertSessionHasErrors(['member_admin', 'org_mail.0']);
});

// --- Authority follows the assignment -------------------------------------------------

/** Whether the Member's next request carries the Members pages (the `administer-members` gate). */
function opensMembersPages(Member $member): bool
{
    $props = test()->actingAs($member)->get('/dashboard')->assertOk()->viewData('page')['props'];

    $railHasMembers = collect(data_get($props, 'rail.officer.items', []))->contains('key', 'members');

    expect(data_get($props, 'auth.can.administerMembers'))->toBe($railHasMembers);

    return $railHasMembers;
}

/** The Directory Audience keys the Member may pick in the Broadcast composer. */
function directoryAudienceKeys(Member $member): array
{
    return collect(test()->actingAs($member)->getJson('/audiences?context=directory')->assertOk()->json('audiences'))
        ->pluck('key')->unique()->values()->all();
}

it('moves the Members pages with member administration', function () {
    $org = stewardshipsOrg();
    steward($org['records'], StewardshipFunction::MemberAdmin);
    $clerk = stewardshipsMemberOf($org['records'], 'Cam', 'Clerk');
    $winner = stewardshipsMemberOf($org['awards'], 'Wyn', 'Winner');

    expect(opensMembersPages($clerk))->toBeTrue()
        ->and(opensMembersPages($winner))->toBeFalse();

    $this->actingAs(Member::factory()->superTier()->create())
        ->put('/officer/settings/stewardships/member_admin', ['group_id' => $org['awards']->id])
        ->assertSessionHasNoErrors();

    expect(opensMembersPages($clerk->fresh()))->toBeFalse()
        ->and(opensMembersPages($winner->fresh()))->toBeTrue();
});

it('moves the org-wide Broadcast audiences with org-wide sender', function () {
    $org = stewardshipsOrg();
    steward($org['records'], StewardshipFunction::OrgMail);
    $clerk = stewardshipsMemberOf($org['records'], 'Cam', 'Clerk');
    $winner = stewardshipsMemberOf($org['awards'], 'Wyn', 'Winner');

    expect(directoryAudienceKeys($clerk))->toContain('all_members')
        ->and(directoryAudienceKeys($winner))->not->toContain('all_members');

    $this->actingAs(Member::factory()->superTier()->create())
        ->put('/officer/settings/stewardships/org_mail', ['group_ids' => [$org['awards']->id]])
        ->assertSessionHasNoErrors();

    expect(directoryAudienceKeys($clerk->fresh()))->not->toContain('all_members')
        ->and(directoryAudienceKeys($winner->fresh()))->toContain('all_members');
});

// --- Access ---------------------------------------------------------------------------

it('forbids the write to anyone but the super-tier', function () {
    $org = stewardshipsOrg();
    steward($org['records'], StewardshipFunction::MemberAdmin);
    $clerk = stewardshipsMemberOf($org['records'], 'Cam', 'Clerk');

    $this->actingAs($clerk)
        ->put('/officer/settings/stewardships/member_admin', ['group_id' => $org['awards']->id])
        ->assertForbidden();
    $this->actingAs($clerk)
        ->put('/officer/settings/stewardships/org_mail', ['group_ids' => [$org['awards']->id]])
        ->assertForbidden();

    expect(Group::stewardOf(StewardshipFunction::MemberAdmin)->is($org['records']))->toBeTrue();
});

it('has no write for the hidden stewardships', function (string $function) {
    $org = stewardshipsOrg();

    $this->actingAs(Member::factory()->superTier()->create())
        ->put("/officer/settings/stewardships/{$function}", ['group_ids' => [$org['awards']->id]])
        // No PUT route matches; the GET-only language fallback answers 405.
        ->assertMethodNotAllowed();

    $this->assertDatabaseCount('group_stewardships', 0);
})->with(['statistics', 'website']);
