<?php

use App\Enums\Category;
use App\Enums\ListingVisibility;
use App\Enums\MeetingLinkKind;
use App\Enums\Role;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\Meeting;
use App\Models\Member;
use Illuminate\Support\Collection;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * Group Meetings tab (#190, PRD #186). The Group's first own-data capability,
 * asserted at the Inertia prop seam: the list is members-only (visible to Group
 * members and the super-tier; a non-member gets the tab with a join notice and no
 * Meeting data, ADR-0037 §2), present only when the
 * Group runs meetings, and the published/hidden flag is respected for ordinary
 * members.
 */

/**
 * Make a member of the given Group (Full standing by default).
 */
function memberOf(Group $group): Member
{
    $member = Member::factory()->create();
    GroupMember::factory()->create(['group_id' => $group->id, 'member_id' => $member->id]);

    return $member;
}

it('lists a meetings-Group meetings for a Group member', function () {
    $group = Group::factory()->workingGroup()->create();
    $meeting = Meeting::factory()->create(['group_id' => $group->id, 'title' => 'Spring planning']);

    $this->actingAs(memberOf($group))
        ->get(route('groups.show', ['group' => $group, 'section' => 'meetings']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('groups/Show')
            ->where('section', 'meetings')
            ->where('meetings', fn (Collection $meetings) => $meetings->contains('title', $meeting->title)));
});

it('shows a non-member the members-only Meetings tab with no Meeting data (ADR-0037 §2)', function () {
    $group = Group::factory()->workingGroup()->create();
    Meeting::factory()->create(['group_id' => $group->id, 'title' => 'Spring planning']);

    $this->actingAs(Member::factory()->create())
        ->get(route('groups.show', ['group' => $group, 'section' => 'meetings']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('section', 'meetings')
            ->where('group.capabilities.meetings', true)
            ->where('membersOnly.meetings', true)
            ->where('meetings', []));

    expect(__('group.members_only', [], 'en'))->toBe('For members of this group')
        ->and(__('group.members_only', [], 'fr'))->toBe('Réservé aux membres de ce groupe')
        ->and(__('group.how_to_join', [], 'fr'))->toBe('Comment se joindre');
});

it('404s a non-member of a Private Group on the Meetings section', function () {
    $group = Group::factory()->workingGroup()->create(['listing_visibility' => ListingVisibility::Private]);

    $this->actingAs(Member::factory()->create())
        ->get(route('groups.show', ['group' => $group, 'section' => 'meetings']))
        ->assertNotFound();
});

it('shows the meetings list to the super-tier without membership', function () {
    $group = Group::factory()->workingGroup()->create();
    Meeting::factory()->create(['group_id' => $group->id]);

    $this->actingAs(Member::factory()->superTier()->create())
        ->get(route('groups.show', ['group' => $group, 'section' => 'meetings']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('meetings', 1));
});

it('opens the root DMV Group meetings to a listed Member with no stored root row', function () {
    // Root-DMV membership is derived from Category, never stored (ADR-0020 §B, ADR-0036 §9):
    // an Active Member with no memberships at all still reaches the org-wide meetings.
    $root = Group::factory()->standingCommittee()->create(['slug' => Group::ROOT_SLUG, 'name' => 'DMV', 'has_meetings' => true]);
    $meeting = Meeting::factory()->create(['group_id' => $root->id, 'title' => 'Annual general meeting']);

    $this->actingAs(Member::factory()->create())
        ->get(route('groups.show', ['group' => $root, 'section' => 'meetings']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('membersOnly.meetings', false)
            ->where('meetings', fn (Collection $meetings) => $meetings->contains('title', $meeting->title)));
});

it('shows the root DMV Group join notice to a Member whose Category grants no listing', function () {
    $root = Group::factory()->standingCommittee()->create(['slug' => Group::ROOT_SLUG, 'name' => 'DMV', 'has_meetings' => true]);
    Meeting::factory()->create(['group_id' => $root->id]);

    $this->actingAs(Member::factory()->category(Category::Provisional)->create())
        ->get(route('groups.show', ['group' => $root, 'section' => 'meetings']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('membersOnly.meetings', true)
            ->where('meetings', []));
});

it('404s the meetings section on a Group that does not run meetings', function () {
    $group = Group::factory()->create(['has_meetings' => false]);

    $this->actingAs(memberOf($group))
        ->get(route('groups.show', ['group' => $group, 'section' => 'meetings']))
        ->assertNotFound();

    $this->actingAs(Member::factory()->superTier()->create())
        ->get(route('groups.show', ['group' => $group, 'section' => 'meetings']))
        ->assertNotFound();
});

it('hides an unpublished meeting from an ordinary member', function () {
    $group = Group::factory()->workingGroup()->create();
    Meeting::factory()->create(['group_id' => $group->id, 'title' => 'Published meeting']);
    Meeting::factory()->hidden()->create(['group_id' => $group->id, 'title' => 'Draft meeting']);

    $this->actingAs(memberOf($group))
        ->get(route('groups.show', ['group' => $group, 'section' => 'meetings']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('meetings', function (Collection $meetings) {
                $titles = $meetings->pluck('title')->all();

                return in_array('Published meeting', $titles, true)
                    && ! in_array('Draft meeting', $titles, true);
            }));
});

it('carries each meeting location, video link, and agenda/minutes/report links', function () {
    $group = Group::factory()->workingGroup()->create();
    $meeting = Meeting::factory()->create([
        'group_id' => $group->id,
        'location' => 'Room 204',
        'video_url' => 'https://example.test/call',
    ]);
    $meeting->links()->create(['kind' => MeetingLinkKind::Agenda, 'url' => 'https://example.test/agenda']);
    $meeting->links()->create(['kind' => MeetingLinkKind::Minutes, 'url' => 'https://example.test/minutes']);

    $this->actingAs(memberOf($group))
        ->get(route('groups.show', ['group' => $group, 'section' => 'meetings']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('meetings.0.location', 'Room 204')
            ->where('meetings.0.video_url', 'https://example.test/call')
            ->where('meetings.0.links', fn (Collection $links) => $links->contains('kind', MeetingLinkKind::Agenda->value)
                && $links->contains('kind', MeetingLinkKind::Minutes->value)));
});

it('leaves the meetings prop empty on the Overview section', function () {
    $group = Group::factory()->workingGroup()->create();
    Meeting::factory()->create(['group_id' => $group->id]);

    $this->actingAs(memberOf($group))
        ->get(route('groups.show', $group))
        ->assertInertia(fn (Assert $page) => $page->where('meetings', []));
});

// --- Ordering ---------------------------------------------------------------
//
// The tab answers "when do we next meet" first and is an archive second, so the
// list is upcoming soonest-first, then past most-recent-first — never the order
// the rows happened to be entered in.

it('orders meetings upcoming soonest-first, then past most-recent-first', function () {
    $group = Group::factory()->workingGroup()->create();

    // Created deliberately out of order, so entry order cannot pass by accident.
    foreach ([
        'Last year' => '-12 months',
        'Furthest ahead' => '+3 months',
        'Last week' => '-1 week',
        'Next week' => '+1 week',
    ] as $title => $offset) {
        Meeting::factory()->create([
            'group_id' => $group->id,
            'title' => $title,
            'held_at' => now()->modify($offset),
        ]);
    }

    $this->actingAs(memberOf($group))
        ->get(route('groups.show', ['group' => $group, 'section' => 'meetings']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('meetings', fn (Collection $meetings) => $meetings->pluck('title')->all() === [
                'Next week',
                'Furthest ahead',
                'Last week',
                'Last year',
            ])->etc());
});

it('marks each meeting as upcoming or past for the client to head the two blocks', function () {
    $group = Group::factory()->workingGroup()->create();
    Meeting::factory()->create(['group_id' => $group->id, 'title' => 'Ahead', 'held_at' => now()->addWeek()]);
    Meeting::factory()->create(['group_id' => $group->id, 'title' => 'Behind', 'held_at' => now()->subWeek()]);

    $this->actingAs(memberOf($group))
        ->get(route('groups.show', ['group' => $group, 'section' => 'meetings']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('meetings.0.is_upcoming', true)
            ->where('meetings.1.is_upcoming', false)
            ->etc());
});

// The Meetings tab hint (ADR-0037 §2): the tab shows wherever the Group runs meetings; the
// members-only flag tells the tab to show the join notice instead of the list.
it('sends a Group member the Meetings tab with its content', function () {
    $group = Group::factory()->workingGroup()->create();

    $this->actingAs(memberOf($group))
        ->get(route('groups.show', ['group' => $group]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('group.capabilities.meetings', true)
            ->where('membersOnly.meetings', false));
});

it('flags the Meetings tab members-only for a non-member', function () {
    $group = Group::factory()->workingGroup()->create();

    $this->actingAs(Member::factory()->create())
        ->get(route('groups.show', ['group' => $group]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('group.capabilities.meetings', true)
            ->where('membersOnly.meetings', true));
});

it('flags the Meetings tab members-only for a member of the parent Group only', function () {
    $parent = Group::factory()->workingGroup()->create();
    $subgroup = Group::factory()->workingGroup()->create(['parent_id' => $parent->id]);

    $this->actingAs(memberOf($parent))
        ->get(route('groups.show', ['group' => $subgroup]))
        ->assertInertia(fn (Assert $page) => $page->where('membersOnly.meetings', true));
});

it('opens the Meetings tab to the super-tier without membership', function () {
    $group = Group::factory()->workingGroup()->create();

    $this->actingAs(Member::factory()->superTier()->create())
        ->get(route('groups.show', ['group' => $group]))
        ->assertInertia(fn (Assert $page) => $page->where('membersOnly.meetings', false));
});

it('sends no members-only flag on a Group that does not run meetings', function () {
    $group = Group::factory()->create(['has_meetings' => false]);

    $this->actingAs(Member::factory()->create())
        ->get(route('groups.show', ['group' => $group]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('group.capabilities.meetings', false)
            ->where('membersOnly.meetings', false));
});

// --- Inactive Groups are read-only (#839, ADR-0036 §2) ------------------------

it('refuses a Chair a new meeting on an archived Group while the list still reads', function () {
    $group = Group::factory()->workingGroup()->archived()->create();
    $meeting = Meeting::factory()->create(['group_id' => $group->id]);
    $chair = memberOf($group);
    GroupMember::where(['group_id' => $group->id, 'member_id' => $chair->id])->sole()->roles()->create(['role' => Role::Chair]);

    $this->actingAs($chair)
        ->get(route('groups.show', ['group' => $group, 'section' => 'meetings']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('meetings', 1)->where('can.createMeeting', false));

    $this->actingAs($chair)
        ->post(route('meetings.store', $group), ['title' => 'Too late', 'held_at' => '2026-07-01 14:00:00'])
        ->assertForbidden();

    $this->actingAs($chair)
        ->delete(route('meetings.destroy', $meeting))
        ->assertForbidden();

    expect(Meeting::count())->toBe(1);
});

it('refuses the super-tier a new meeting on an expired time-boxed Group', function () {
    $group = Group::factory()->workingGroup()->timeBoxed()->create(['end_date' => now()->subDay()->toDateString()]);

    $this->actingAs(Member::factory()->superTier()->create())
        ->post(route('meetings.store', $group), ['title' => 'Too late', 'held_at' => '2026-07-01 14:00:00'])
        ->assertForbidden();

    expect(Meeting::count())->toBe(0);
});
