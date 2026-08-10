<?php

namespace App\Policies;

use App\Enums\ListingVisibility;
use App\Enums\Role;
use App\Enums\ScheduleState;
use App\Models\Group;
use App\Models\Member;
use App\Models\Schedule;

/**
 * Authorization for a Group's Schedules (#353, PRD #352, ADR-0021 §1). The read side
 * only — authoring lands in the next slice.
 *
 * The Scheduling section is org-open, deliberately *not* Meetings' members-only gate:
 * the tab renders whenever the Group runs scheduling. The per-Schedule read splits by
 * state — a `draft` is admin-only (the Group's Scheduler / Chair, plus the super-tier),
 * while a `published` Schedule follows the Group's `listing_visibility` audience. So you
 * cannot read the Schedule of a Group you cannot see, and a parent Group's Chair who is
 * not a member here reads nothing (parentage is structural authority, never content
 * read — ADR-0019).
 *
 * The super-tier short-circuit lives in a single `Gate::before` (AppServiceProvider)
 * and is never re-checked here.
 */
class SchedulePolicy
{
    /**
     * Who may reach the Scheduling section at all: anyone, while the Group runs
     * scheduling. Unlike the members-only Meetings gate this is org-open — the
     * Private-Group boundary is enforced once at the page gate (GroupController),
     * not restated here. The capability guard is the whole gate: there is no section
     * to render when the Group runs no scheduling.
     */
    public function viewAny(Member $actor, Group $group): bool
    {
        return $group->has_scheduling;
    }

    /**
     * Who may read one Schedule. A `draft` is visible only to the Group's schedule
     * admins; a `published` one is visible to the Group's listing-visibility audience.
     * Authority never leaks across Groups, and parentage grants no content read.
     */
    public function view(Member $actor, Schedule $schedule): bool
    {
        if ($schedule->state === ScheduleState::Draft) {
            return $this->administersSchedulingFor($actor, $schedule->group);
        }

        return $this->canReadListing($actor, $schedule->group);
    }

    /**
     * The schedule-admin gate: the Group runs scheduling *and* the actor can act as
     * its Scheduler (Chair-implication folded in by {@see Member::canActAs()}). The
     * capability guard matters because Chair-implication would otherwise grant schedule
     * powers on a non-scheduling Group the member chairs. The read side uses this to
     * reveal drafts; the authoring slice will reuse the same predicate.
     */
    private function administersSchedulingFor(Member $actor, Group $group): bool
    {
        return $group->has_scheduling
            && $actor->canActAs(Role::Scheduler, $group);
    }

    /**
     * Whether the actor is inside the Group's listing-visibility audience (ADR-0019):
     * Public and Group visibility are org-open, so any logged-in Member reads a
     * published Schedule; a Private Group discloses nothing to a non-member, so its
     * Schedule is unreadable to one — consistent with the Group page's own gate.
     */
    private function canReadListing(Member $actor, Group $group): bool
    {
        return $group->listing_visibility !== ListingVisibility::Private
            || $actor->membershipIn($group) !== null;
    }
}
