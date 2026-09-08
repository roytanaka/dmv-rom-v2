<?php

use App\Enums\MeetingLinkKind;
use App\Enums\Role;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\GroupMemberRole;
use App\Models\Meeting;
use App\Models\Member;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * Role-matrix HTTP harness for officer meetings CRUD (#193, PRD #186).
 *
 * Exercises create / edit / delete through every layer — route → auth middleware →
 * Form Request authorize() → MeetingPolicy → Gate::before. Management is gated to a
 * Group's Secretary | Chair | super-tier and requires the Group's `has_meetings`
 * capability; everyone else is denied. The deny rows (ordinary member, officer of
 * another Group, capability off, unauthenticated) prove the fail-closed posture.
 * Link attachment, the published/hidden toggle, and the `can` UI hints round it out.
 */

/** A Group that runs meetings — the only Kind a meeting may be managed on. */
function meetingsGroup(): Group
{
    return Group::factory()->workingGroup()->create();
}

/** A member of the given Group, holding the given role. */
function meetingOfficerOf(Group $group, Role $role): Member
{
    $member = Member::factory()->create();
    $membership = GroupMember::factory()->create(['group_id' => $group->id, 'member_id' => $member->id]);
    GroupMemberRole::factory()->role($role)->create(['group_member_id' => $membership->id]);

    return $member;
}

/** An ordinary member of the given Group, holding no role. */
function meetingMemberOf(Group $group): Member
{
    $member = Member::factory()->create();
    GroupMember::factory()->create(['group_id' => $group->id, 'member_id' => $member->id]);

    return $member;
}

$payload = ['title' => 'Spring planning', 'held_at' => '2026-07-01 14:00:00'];

// --- Creating (store) — allow rows ------------------------------------------

it('lets a Secretary create a meeting on their meetings-Group', function () use ($payload) {
    $group = meetingsGroup();

    $this->actingAs(meetingOfficerOf($group, Role::Secretary))
        ->post(route('meetings.store', $group), $payload)
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect(Meeting::sole()->group_id)->toBe($group->id);
});

it('lets a Chair create a meeting (Chair-implication)', function () use ($payload) {
    $group = meetingsGroup();

    $this->actingAs(meetingOfficerOf($group, Role::Chair))
        ->post(route('meetings.store', $group), $payload)
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect(Meeting::count())->toBe(1);
});

it('lets a super-tier member create a meeting on any meetings-Group', function () use ($payload) {
    $group = meetingsGroup();

    $this->actingAs(Member::factory()->superTier()->create())
        ->post(route('meetings.store', $group), $payload)
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect(Meeting::count())->toBe(1);
});

// --- Creating (store) — deny rows (required) --------------------------------

it('forbids an ordinary member from creating a meeting', function () use ($payload) {
    $group = meetingsGroup();

    $this->actingAs(meetingMemberOf($group))
        ->post(route('meetings.store', $group), $payload)
        ->assertForbidden();

    expect(Meeting::count())->toBe(0);
});

it('forbids a Secretary of one Group from creating on another', function () use ($payload) {
    $secretary = meetingOfficerOf(meetingsGroup(), Role::Secretary);
    $other = meetingsGroup();

    $this->actingAs($secretary)
        ->post(route('meetings.store', $other), $payload)
        ->assertForbidden();

    expect(Meeting::count())->toBe(0);
});

it('forbids creating a meeting on a Group that does not run meetings', function () use ($payload) {
    // A Chair (Chair-implication would grant officer powers) of a Group whose
    // meetings capability is off — there is nothing to manage.
    $group = Group::factory()->create(['has_meetings' => false]);

    $this->actingAs(meetingOfficerOf($group, Role::Chair))
        ->post(route('meetings.store', $group), $payload)
        ->assertForbidden();

    expect(Meeting::count())->toBe(0);
});

it('redirects an unauthenticated create to login', function () use ($payload) {
    $this->post(route('meetings.store', meetingsGroup()), $payload)
        ->assertRedirect(route('login'));
});

// --- Editing (update) -------------------------------------------------------

it('lets a Secretary edit a meeting on their Group', function () {
    $group = meetingsGroup();
    $meeting = Meeting::factory()->create(['group_id' => $group->id, 'title' => 'Old']);

    $this->actingAs(meetingOfficerOf($group, Role::Secretary))
        ->patch(route('meetings.update', $meeting), ['title' => 'New', 'held_at' => '2026-08-01 09:00:00'])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect($meeting->fresh()->title)->toBe('New');
});

it('lets a super-tier member edit any meeting', function () {
    $meeting = Meeting::factory()->create(['title' => 'Old']);

    $this->actingAs(Member::factory()->superTier()->create())
        ->patch(route('meetings.update', $meeting), ['title' => 'New', 'held_at' => '2026-08-01 09:00:00'])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect($meeting->fresh()->title)->toBe('New');
});

it('forbids a Secretary of another Group from editing a meeting', function () {
    $meeting = Meeting::factory()->create(['group_id' => meetingsGroup()->id, 'title' => 'Old']);

    $this->actingAs(meetingOfficerOf(meetingsGroup(), Role::Secretary))
        ->patch(route('meetings.update', $meeting), ['title' => 'New', 'held_at' => '2026-08-01 09:00:00'])
        ->assertForbidden();

    expect($meeting->fresh()->title)->toBe('Old');
});

it('forbids an ordinary member from editing a meeting', function () {
    $group = meetingsGroup();
    $meeting = Meeting::factory()->create(['group_id' => $group->id, 'title' => 'Old']);

    $this->actingAs(meetingMemberOf($group))
        ->patch(route('meetings.update', $meeting), ['title' => 'New', 'held_at' => '2026-08-01 09:00:00'])
        ->assertForbidden();

    expect($meeting->fresh()->title)->toBe('Old');
});

it('forbids editing a meeting once the Group no longer runs meetings', function () {
    $group = meetingsGroup();
    $meeting = Meeting::factory()->create(['group_id' => $group->id, 'title' => 'Old']);
    $secretary = meetingOfficerOf($group, Role::Secretary);
    $group->update(['has_meetings' => false]);

    $this->actingAs($secretary)
        ->patch(route('meetings.update', $meeting), ['title' => 'New', 'held_at' => '2026-08-01 09:00:00'])
        ->assertForbidden();

    expect($meeting->fresh()->title)->toBe('Old');
});

it('redirects an unauthenticated edit to login', function () {
    $meeting = Meeting::factory()->create();

    $this->patch(route('meetings.update', $meeting), ['title' => 'New', 'held_at' => '2026-08-01 09:00:00'])
        ->assertRedirect(route('login'));
});

// --- Deleting (destroy) -----------------------------------------------------

it('lets a Secretary delete a meeting on their Group', function () {
    $group = meetingsGroup();
    $meeting = Meeting::factory()->create(['group_id' => $group->id]);

    $this->actingAs(meetingOfficerOf($group, Role::Secretary))
        ->delete(route('meetings.destroy', $meeting))
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect(Meeting::count())->toBe(0);
});

it('lets a super-tier member delete any meeting', function () {
    $meeting = Meeting::factory()->create();

    $this->actingAs(Member::factory()->superTier()->create())
        ->delete(route('meetings.destroy', $meeting))
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect(Meeting::count())->toBe(0);
});

it('forbids a Secretary of another Group from deleting a meeting', function () {
    $meeting = Meeting::factory()->create(['group_id' => meetingsGroup()->id]);

    $this->actingAs(meetingOfficerOf(meetingsGroup(), Role::Secretary))
        ->delete(route('meetings.destroy', $meeting))
        ->assertForbidden();

    expect(Meeting::count())->toBe(1);
});

it('forbids an ordinary member from deleting a meeting', function () {
    $group = meetingsGroup();
    $meeting = Meeting::factory()->create(['group_id' => $group->id]);

    $this->actingAs(meetingMemberOf($group))
        ->delete(route('meetings.destroy', $meeting))
        ->assertForbidden();

    expect(Meeting::count())->toBe(1);
});

it('redirects an unauthenticated delete to login', function () {
    $meeting = Meeting::factory()->create();

    $this->delete(route('meetings.destroy', $meeting))->assertRedirect(route('login'));
});

// --- Links, drafting, and `can` hints ---------------------------------------

it('attaches agenda / minutes / report links when creating a meeting', function () use ($payload) {
    $group = meetingsGroup();

    $this->actingAs(meetingOfficerOf($group, Role::Secretary))
        ->post(route('meetings.store', $group), [
            ...$payload,
            'links' => [
                ['kind' => MeetingLinkKind::Agenda->value, 'url' => 'https://example.test/agenda'],
                ['kind' => MeetingLinkKind::Minutes->value, 'url' => 'https://example.test/minutes'],
            ],
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $links = Meeting::sole()->links;
    expect($links)->toHaveCount(2)
        ->and($links->pluck('kind')->all())->toContain(MeetingLinkKind::Agenda, MeetingLinkKind::Minutes);
});

it("replaces a meeting's links when editing it", function () {
    $group = meetingsGroup();
    $meeting = Meeting::factory()->create(['group_id' => $group->id]);
    $meeting->links()->create(['kind' => MeetingLinkKind::Agenda, 'url' => 'https://example.test/old']);

    $this->actingAs(meetingOfficerOf($group, Role::Secretary))
        ->patch(route('meetings.update', $meeting), [
            'title' => $meeting->title,
            'held_at' => '2026-08-01 09:00:00',
            'links' => [['kind' => MeetingLinkKind::Minutes->value, 'url' => 'https://example.test/new']],
        ])
        ->assertSessionHasNoErrors();

    $links = $meeting->fresh()->links;
    expect($links)->toHaveCount(1)
        ->and($links->first()->kind)->toBe(MeetingLinkKind::Minutes)
        ->and($links->first()->url)->toBe('https://example.test/new');
});

it('lets an officer save a meeting as a hidden draft', function () use ($payload) {
    $group = meetingsGroup();

    $this->actingAs(meetingOfficerOf($group, Role::Secretary))
        ->post(route('meetings.store', $group), [...$payload, 'is_published' => false])
        ->assertSessionHasNoErrors();

    expect(Meeting::sole()->is_published)->toBeFalse();
});

it('shows an officer their Group hidden draft meetings', function () {
    $group = meetingsGroup();
    Meeting::factory()->hidden()->create(['group_id' => $group->id, 'title' => 'Draft meeting']);

    $this->actingAs(meetingOfficerOf($group, Role::Secretary))
        ->get(route('groups.show', ['group' => $group, 'section' => 'meetings']))
        ->assertInertia(fn (Assert $page) => $page->where('meetings.0.title', 'Draft meeting'));
});

it('hints meeting management on for an officer and off for an ordinary member', function () {
    $group = meetingsGroup();
    Meeting::factory()->create(['group_id' => $group->id]);
    $section = ['group' => $group, 'section' => 'meetings'];

    $this->actingAs(meetingOfficerOf($group, Role::Secretary))
        ->get(route('groups.show', $section))
        ->assertInertia(fn (Assert $page) => $page
            ->where('can.createMeeting', true)
            ->where('meetings.0.can.update', true)
            ->where('meetings.0.can.delete', true));

    $this->actingAs(meetingMemberOf($group))
        ->get(route('groups.show', $section))
        ->assertInertia(fn (Assert $page) => $page
            ->where('can.createMeeting', false)
            ->where('meetings.0.can.update', false));
});

// --- Wall clock (org timezone) ----------------------------------------------
//
// A meeting time is the museum's wall clock. The client's <input type="datetime-local">
// sends it with no offset and it is stored in UTC, so reading it as UTC on the way in
// shifted every meeting back four hours on the way out — 11am entered, 7am shown.

it('reads a naive held_at as the org wall clock and stores it in UTC', function () {
    $group = meetingsGroup();

    $this->actingAs(meetingOfficerOf($group, Role::Secretary))
        ->post(route('meetings.store', $group), ['title' => 'Summer meeting', 'held_at' => '2026-07-01T11:00'])
        ->assertSessionHasNoErrors();

    // 11:00 in Toronto during EDT (UTC-4).
    expect(Meeting::sole()->held_at->toDateTimeString())->toBe('2026-07-01 15:00:00');
});

it('follows daylight saving when reading the org wall clock', function () {
    $group = meetingsGroup();

    $this->actingAs(meetingOfficerOf($group, Role::Secretary))
        ->post(route('meetings.store', $group), ['title' => 'Winter meeting', 'held_at' => '2026-01-15T11:00'])
        ->assertSessionHasNoErrors();

    // The same 11:00, but in EST (UTC-5) — a hardcoded offset would be an hour out.
    expect(Meeting::sole()->held_at->toDateTimeString())->toBe('2026-01-15 16:00:00');
});

it('converts a held_at that carries its own offset rather than reinterpreting it', function () {
    $group = meetingsGroup();

    $this->actingAs(meetingOfficerOf($group, Role::Secretary))
        ->post(route('meetings.store', $group), ['title' => 'Explicit', 'held_at' => '2026-07-01T11:00:00Z'])
        ->assertSessionHasNoErrors();

    expect(Meeting::sole()->held_at->toDateTimeString())->toBe('2026-07-01 11:00:00');
});

it('reads an edited held_at on the org wall clock too', function () {
    $group = meetingsGroup();
    $meeting = Meeting::factory()->create(['group_id' => $group->id]);

    $this->actingAs(meetingOfficerOf($group, Role::Secretary))
        ->patch(route('meetings.update', $meeting), ['title' => $meeting->title, 'held_at' => '2026-07-01T11:00'])
        ->assertSessionHasNoErrors();

    expect($meeting->fresh()->held_at->toDateTimeString())->toBe('2026-07-01 15:00:00');
});

it('still rejects an unparseable held_at as a validation error', function () {
    $group = meetingsGroup();

    $this->actingAs(meetingOfficerOf($group, Role::Secretary))
        ->post(route('meetings.store', $group), ['title' => 'Bad date', 'held_at' => 'not a date'])
        ->assertSessionHasErrors('held_at');
});

it('shares the org timezone so the client formats on the same wall clock', function () {
    $group = meetingsGroup();

    $this->actingAs(meetingMemberOf($group))
        ->get(route('groups.show', ['group' => $group, 'section' => 'meetings']))
        ->assertInertia(fn (Assert $page) => $page->where('timezone', 'America/Toronto'));
});
