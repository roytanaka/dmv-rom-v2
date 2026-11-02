<?php

use App\Enums\Category;
use App\Enums\Kind;
use App\Enums\LifecycleState;
use App\Enums\MembershipStatus;
use App\Enums\Reply;
use App\Enums\Role;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\Guest;
use App\Models\Invitation;
use App\Models\Member;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * Picking Guests (#903, spec #901, ADR-0034 §3): a Host builds the guest list of an
 * Invitation from single Members and from whole Audiences. Acts through the HTTP routes
 * as a Member and checks Inertia props.
 */

function hostedInvitation(?Member $host = null): Invitation
{
    return Invitation::factory()->create(['host_id' => ($host ?? Member::factory()->create())->id]);
}

/**
 * The Member names on the guest-list page, as the Host sees them.
 *
 * @return list<string>
 */
function guestNames(Member $viewer, Invitation $invitation): array
{
    $names = [];

    test()->actingAs($viewer)
        ->get(route('invitations.guests', ['invitation' => $invitation->id]))
        ->assertOk()
        ->assertInertia(function (Assert $page) use (&$names) {
            $page->component('invitations/Guests');
            $names = array_column($page->toArray()['props']['guests'], 'name');
        });

    return $names;
}

// --- A single Member ---------------------------------------------------------

it('lets a Host add any single current Member as a Guest', function () {
    $invitation = hostedInvitation();
    $guest = Member::factory()->create(['first_name' => 'Ada', 'last_name' => 'Lee']);

    $this->actingAs($invitation->host)
        ->post(route('invitations.guests.store', ['invitation' => $invitation->id]), ['member' => $guest->id])
        ->assertRedirect(route('invitations.guests', ['invitation' => $invitation->id]));

    expect(guestNames($invitation->host, $invitation))->toBe(['Ada Lee']);
});

it('refuses a Member who is not current as a single pick', function (Category $category) {
    $invitation = hostedInvitation();
    $member = Member::factory()->category($category)->create();

    $this->actingAs($invitation->host)
        ->post(route('invitations.guests.store', ['invitation' => $invitation->id]), ['member' => $member->id])
        ->assertSessionHasErrors('member');

    expect(guestNames($invitation->host, $invitation))->toBe([]);
})->with([
    'resigned' => [Category::Resigned],
    'deceased' => [Category::Deceased],
    'provisional, who has no Directory listing' => [Category::Provisional],
]);

it('leaves one Guest, with their Reply, when the same Member is added twice', function () {
    $invitation = hostedInvitation();
    $guest = Member::factory()->create(['first_name' => 'Ada', 'last_name' => 'Lee']);
    Guest::factory()->create(['invitation_id' => $invitation->id, 'member_id' => $guest->id, 'reply' => Reply::Accepted]);

    $this->actingAs($invitation->host)
        ->post(route('invitations.guests.store', ['invitation' => $invitation->id]), ['member' => $guest->id])
        ->assertRedirect();

    expect(guestNames($invitation->host, $invitation))->toBe(['Ada Lee'])
        ->and(Guest::sole()->reply)->toBe(Reply::Accepted);
});

// --- Who may touch the list ---------------------------------------------------

it('keeps the guest list and its writes from everyone but the Host', function (string $who) {
    $invitation = hostedInvitation();
    $guest = Guest::factory()->create(['invitation_id' => $invitation->id]);
    $member = Member::factory()->create();
    $params = ['invitation' => $invitation->id];

    $actor = match ($who) {
        'member' => Member::factory()->create(),
        'guest' => $guest->member,
        'demoted host' => tap($invitation->host)->update(['category' => Category::Provisional]),
    };

    $this->actingAs($actor)->get(route('invitations.guests', $params))->assertForbidden();
    $this->post(route('invitations.guests.store', $params), ['member' => $member->id])->assertForbidden();

    expect(Guest::sole()->is($guest))->toBeTrue();
})->with(['member', 'guest', 'demoted host']);

it('lets the super-tier manage any guest list', function () {
    $invitation = hostedInvitation();
    $superTier = Member::factory()->superTier()->create();
    $member = Member::factory()->create(['first_name' => 'Ada', 'last_name' => 'Lee']);

    $this->actingAs($superTier)
        ->post(route('invitations.guests.store', ['invitation' => $invitation->id]), ['member' => $member->id])
        ->assertRedirect();

    expect(guestNames($superTier, $invitation))->toBe(['Ada Lee']);
});

it('serves the guest list under a translated French URL', function () {
    $invitation = hostedInvitation();
    $guest = Guest::factory()->create(['invitation_id' => $invitation->id]);

    $this->withLocaleRoutes('fr', function () use ($invitation, $guest) {
        $this->actingAs($invitation->host);
        $list = "/fr/invitations/{$invitation->id}/invites";

        $this->get($list)->assertOk();
        $this->post($list, ['member' => Member::factory()->create()->id])->assertRedirect($list);
        $this->post("{$list}/audience", ['audience' => 'whole_group', 'parameter' => 'nope'])->assertForbidden();
        $this->delete("{$list}/{$guest->id}")->assertRedirect($list);
    });
});

// --- Whole Audiences ----------------------------------------------------------

function rosterOf(Group $group, Member ...$members): void
{
    foreach ($members as $member) {
        GroupMember::factory()->status(MembershipStatus::Full)->create(['group_id' => $group->id, 'member_id' => $member->id]);
    }
}

function chair(): Member
{
    $chair = Member::factory()->create();
    $group = Group::factory()->create();
    GroupMember::factory()->status(MembershipStatus::Full)
        ->create(['group_id' => $group->id, 'member_id' => $chair->id])
        ->roles()->create(['role' => Role::Chair]);

    return $chair->fresh();
}

function addAudience(Invitation $invitation, array $pick): TestResponse
{
    return test()->post(route('invitations.guests.audience', ['invitation' => $invitation->id]), $pick);
}

it('lets a Host add a whole Group they belong to, with its no-email Members', function () {
    $host = Member::factory()->create(['first_name' => 'Hal', 'last_name' => 'Host']);
    $invitation = hostedInvitation($host);
    $group = Group::factory()->create();
    $reached = Member::factory()->create(['first_name' => 'Ada', 'last_name' => 'Lee']);
    $silent = Member::factory()->create(['first_name' => 'Bo', 'last_name' => 'Ray', 'no_email' => true]);
    rosterOf($group, $host, $reached, $silent);

    $this->actingAs($host);
    addAudience($invitation, ['audience' => 'whole_group', 'parameter' => $group->slug])
        ->assertRedirect(route('invitations.guests', ['invitation' => $invitation->id]));

    expect(guestNames($host, $invitation))->toBe(['Hal Host', 'Ada Lee', 'Bo Ray']);
});

it('leaves departed Members out of a whole Group', function () {
    $host = Member::factory()->create(['first_name' => 'Hal', 'last_name' => 'Host']);
    $invitation = hostedInvitation($host);
    $group = Group::factory()->create();
    rosterOf($group, $host, Member::factory()->category(Category::Resigned)->create());

    $this->actingAs($host);
    addAudience($invitation, ['audience' => 'whole_group', 'parameter' => $group->slug])->assertRedirect();

    expect(guestNames($host, $invitation))->toBe(['Hal Host']);
});

it('refuses a Group the Host does not belong to', function () {
    $invitation = hostedInvitation();
    $group = Group::factory()->create();
    rosterOf($group, Member::factory()->create());

    $this->actingAs($invitation->host);
    addAudience($invitation, ['audience' => 'whole_group', 'parameter' => $group->slug])->assertForbidden();

    expect(Guest::count())->toBe(0);
});

it('lets an org-wide sender add any Group', function () {
    $invitation = hostedInvitation(chair());
    $group = Group::factory()->create();
    rosterOf($group, Member::factory()->create(['first_name' => 'Ada', 'last_name' => 'Lee']));

    $this->actingAs($invitation->host);
    addAudience($invitation, ['audience' => 'whole_group', 'parameter' => $group->slug])->assertRedirect();

    expect(guestNames($invitation->host, $invitation))->toBe(['Ada Lee']);
});

it('lets an org-wide sender add All Members, without those on leave or departed', function () {
    $host = chair();
    $invitation = hostedInvitation($host);
    $provisional = Member::factory()->category(Category::Provisional)->create();
    Member::factory()->category(Category::Loa)->create();
    Member::factory()->category(Category::Resigned)->create();

    $this->actingAs($host);
    addAudience($invitation, ['audience' => 'all_members'])->assertRedirect();

    expect(Guest::pluck('member_id')->sort()->values()->all())
        ->toBe(collect([$host->id, $provisional->id])->sort()->values()->all());
});

it('refuses All Members from a Host who is not an org-wide sender', function () {
    $invitation = hostedInvitation();
    Member::factory()->count(2)->create();

    $this->actingAs($invitation->host);
    addAudience($invitation, ['audience' => 'all_members'])->assertForbidden();

    expect(Guest::count())->toBe(0);
});

it('takes only the Audiences a guest list offers', function () {
    $invitation = hostedInvitation(chair());

    $this->actingAs($invitation->host);
    addAudience($invitation, ['audience' => 'board_of_directors'])->assertSessionHasErrors('audience');

    expect(Guest::count())->toBe(0);
});

it('keeps one Guest and their Reply when an Audience overlaps the list', function () {
    $host = Member::factory()->create();
    $invitation = hostedInvitation($host);
    $group = Group::factory()->create();
    $overlap = Member::factory()->create();
    rosterOf($group, $host, $overlap);
    Guest::factory()->create(['invitation_id' => $invitation->id, 'member_id' => $overlap->id, 'reply' => Reply::Declined]);

    $this->actingAs($host);
    addAudience($invitation, ['audience' => 'whole_group', 'parameter' => $group->slug])->assertRedirect();
    addAudience($invitation, ['audience' => 'whole_group', 'parameter' => $group->slug])->assertRedirect();

    expect(Guest::count())->toBe(2)
        ->and(Guest::where('member_id', $overlap->id)->sole()->reply)->toBe(Reply::Declined);
});

it('offers a Host only the Audiences they may pick', function () {
    $host = Member::factory()->create();
    $invitation = hostedInvitation($host);
    $mine = Group::factory()->create(['name' => 'Mine']);
    Group::factory()->create(['name' => 'Theirs']);
    rosterOf($mine, $host);

    $this->actingAs($host)
        ->get(route('invitations.guests', ['invitation' => $invitation->id]))
        ->assertInertia(fn (Assert $page) => $page->where('audiences', [
            ['key' => 'whole_group', 'parameter' => $mine->slug, 'label' => 'Mine'],
        ]));
});

it('offers an org-wide sender All Members and every live Group, not the root or Containers', function () {
    $host = chair();
    $invitation = hostedInvitation($host);
    Group::factory()->create(['name' => 'Aardvarks']);
    Group::factory()->create(['name' => 'Bucket', 'kind' => Kind::Container]);
    Group::factory()->create(['name' => 'Old', 'lifecycle_state' => LifecycleState::Archived]);
    Group::factory()->create(['name' => 'DMV', 'slug' => Group::ROOT_SLUG]);

    $this->actingAs($host)
        ->get(route('invitations.guests', ['invitation' => $invitation->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('audiences.0.key', 'all_members')
            ->where('audiences', fn ($audiences) => collect($audiences)->pluck('label')->contains('Aardvarks'))
            ->has('audiences', 3)); // All Members, the Chair's own Group, Aardvarks.
});

// --- Removing ------------------------------------------------------------------

it('removes a Guest and their Reply with them', function () {
    $invitation = hostedInvitation();
    $guest = Guest::factory()->create(['invitation_id' => $invitation->id, 'reply' => Reply::Accepted]);

    $this->actingAs($invitation->host)
        ->delete(route('invitations.guests.destroy', ['invitation' => $invitation->id, 'guest' => $guest->id]))
        ->assertRedirect(route('invitations.guests', ['invitation' => $invitation->id]));

    expect(guestNames($invitation->host, $invitation))->toBe([]);

    // Back on the list, they start over.
    $this->post(route('invitations.guests.store', ['invitation' => $invitation->id]), ['member' => $guest->member_id]);

    expect(Guest::sole()->reply)->toBe(Reply::NoReply);
});

it('refuses to remove a Guest for anyone but the Host', function () {
    $invitation = hostedInvitation();
    $guest = Guest::factory()->create(['invitation_id' => $invitation->id]);

    $this->actingAs($guest->member)
        ->delete(route('invitations.guests.destroy', ['invitation' => $invitation->id, 'guest' => $guest->id]))
        ->assertForbidden();

    expect(Guest::count())->toBe(1);
});

it('will not remove a Guest through another Invitation', function () {
    $invitation = hostedInvitation();
    $guest = Guest::factory()->create();

    $this->actingAs($invitation->host)
        ->delete(route('invitations.guests.destroy', ['invitation' => $invitation->id, 'guest' => $guest->id]))
        ->assertNotFound();

    expect(Guest::count())->toBe(1);
});

// --- Privacy and the single-pick list ------------------------------------------

it('holds no email address in the guest-list payload', function () {
    $invitation = hostedInvitation();
    $guest = Guest::factory()->create(['invitation_id' => $invitation->id]);
    $candidate = Member::factory()->create();
    $group = Group::factory()->create();
    rosterOf($group, $invitation->host);

    $response = $this->actingAs($invitation->host)
        ->get(route('invitations.guests', ['invitation' => $invitation->id]))
        ->assertInertia(fn (Assert $page) => $page->has('guests', 1)->has('candidates', 2));

    $response->assertDontSee($guest->member->email)->assertDontSee($candidate->email);
});

it('offers current Members not yet on the list as single picks', function () {
    $invitation = hostedInvitation(Member::factory()->create(['first_name' => 'Hal', 'last_name' => 'Host']));
    Guest::factory()->create(['invitation_id' => $invitation->id]);
    $candidate = Member::factory()->create(['first_name' => 'Ada', 'last_name' => 'Lee']);
    Member::factory()->category(Category::Provisional)->create();

    $this->actingAs($invitation->host)
        ->get(route('invitations.guests', ['invitation' => $invitation->id]))
        ->assertInertia(fn (Assert $page) => $page->where('candidates', [
            ['id' => $invitation->host->id, 'name' => 'Hal Host'],
            ['id' => $candidate->id, 'name' => 'Ada Lee'],
        ]));
});

it('links a Host to the guest list from Hosting', function () {
    $invitation = hostedInvitation();

    $this->actingAs($invitation->host)
        ->get(route('invitations.hosting'))
        ->assertInertia(fn (Assert $page) => $page->where('invitations.0.can.guests', true));
});
