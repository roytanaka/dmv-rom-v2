<?php

namespace App\Policies;

use App\Enums\ListingVisibility;
use App\Enums\Role;
use App\Enums\ScheduleState;
use App\Models\Group;
use App\Models\Member;
use App\Models\Schedule;

/**
 * Authorization for a Group's Schedules (#353, #354, PRD #352, ADR-0021 §1) — the read
 * surface and the authoring write seam.
 *
 * The Scheduling section is org-open, deliberately *not* Meetings' members-only gate:
 * the tab renders whenever the Group runs scheduling. The per-Schedule read splits by
 * state — a `draft` is admin-only (the Group's Scheduler / Chair, plus the super-tier),
 * while a `published` Schedule follows the Group's `listing_visibility` audience. So you
 * cannot read the Schedule of a Group you cannot see, and a parent Group's Chair who is
 * not a member here reads nothing (parentage is structural authority, never content
 * read — ADR-0019).
 *
 * Every authoring ability (create / update / publish / unpublish / delete) delegates to
 * the same schedule-admin predicate ({@see administersSchedulingFor}): the Group must
 * run scheduling, and the actor must be able to act as Scheduler (Chair-implication
 * folded in by {@see Member::canActAs()}).
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
     * Who may add a Schedule to a Group: a Scheduler or Chair of that Group, and only
     * while its scheduling capability is on. A new Schedule starts as a draft.
     */
    public function create(Member $actor, Group $group): bool
    {
        return $this->administersSchedulingFor($actor, $group);
    }

    /**
     * Who may edit a Schedule's authored fields: a schedule admin of the owning Group.
     * Authority never leaks across Groups — an admin of a different Group is denied.
     */
    public function update(Member $actor, Schedule $schedule): bool
    {
        return $this->administersSchedulingFor($actor, $schedule->group);
    }

    /**
     * Who may publish a draft Schedule (draft → published): a schedule admin of the
     * owning Group. Publishing is purely additive — nothing already read becomes wrong
     * and no Sign-up is disturbed (ADR-0021 §1) — so it carries no further guard.
     */
    public function publish(Member $actor, Schedule $schedule): bool
    {
        return $this->administersSchedulingFor($actor, $schedule->group);
    }

    /**
     * Who may un-publish a Schedule (published → draft): a schedule admin of the owning
     * Group, and only at zero Sign-ups (ADR-0021 §1) — a Chair who published early clears
     * the Sign-ups first, visibly, so a hidden Schedule never leaves live Sign-ups sitting
     * behind it. Bad states are made unreachable rather than unwound.
     */
    public function unpublish(Member $actor, Schedule $schedule): bool
    {
        return $this->administersSchedulingFor($actor, $schedule->group)
            && ! $schedule->signUps()->exists();
    }

    /**
     * Who may delete a Schedule: a schedule admin of the owning Group, and only at zero
     * Sign-ups — the same rule as un-publish, so a Schedule with history is permanent and
     * safe to reference (ADR-0021 §1).
     */
    public function delete(Member $actor, Schedule $schedule): bool
    {
        return $this->administersSchedulingFor($actor, $schedule->group)
            && ! $schedule->signUps()->exists();
    }

    /**
     * Who may edit a Group's Reminder settings (#486, ADR-0024 §7): a schedule admin of the
     * Group — the same Scheduler / Chair gate every authoring ability uses. Reminders are a
     * scheduling concern, so the capability guard folds in: a Chair of a non-scheduling Group
     * has no Reminders to edit. Reached on a Group, not a Schedule, so the caller resolves the
     * policy by the class name (UpdateReminderSettingsRequest).
     */
    public function updateReminders(Member $actor, Group $group): bool
    {
        return $this->administersSchedulingFor($actor, $group);
    }

    /**
     * The schedule-admin gate: the Group runs scheduling *and* the actor can act as
     * its Scheduler (Chair-implication folded in by {@see Member::canActAs()}). The
     * capability guard matters because Chair-implication would otherwise grant schedule
     * powers on a non-scheduling Group the member chairs. The read side uses this to
     * reveal drafts; every authoring ability (create / update / publish / unpublish /
     * delete) reuses the same predicate.
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
