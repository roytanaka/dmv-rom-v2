<?php

use App\Enums\MeetingLinkKind;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\Meeting;
use App\Models\Member;
use Illuminate\Support\Collection;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * Group Meetings tab (#190, PRD #186). The Group's first own-data capability,
 * asserted at the Inertia prop seam: the list is members-only (visible to Group
 * members and the super-tier, withheld from non-members), present only when the
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

it('withholds the meetings list from a non-member', function () {
    $group = Group::factory()->workingGroup()->create();
    Meeting::factory()->create(['group_id' => $group->id]);

    $this->actingAs(Member::factory()->create())
        ->get(route('groups.show', ['group' => $group, 'section' => 'meetings']))
        ->assertForbidden();
});

it('shows the meetings list to the super-tier without membership', function () {
    $group = Group::factory()->workingGroup()->create();
    Meeting::factory()->create(['group_id' => $group->id]);

    $this->actingAs(Member::factory()->superTier()->create())
        ->get(route('groups.show', ['group' => $group, 'section' => 'meetings']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('meetings', 1));
});

it('forbids the meetings section on a Group that does not run meetings', function () {
    $group = Group::factory()->create(['has_meetings' => false]);

    $this->actingAs(memberOf($group))
        ->get(route('groups.show', ['group' => $group, 'section' => 'meetings']))
        ->assertForbidden();
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
