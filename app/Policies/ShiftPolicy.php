<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\Group;
use App\Models\Member;
use App\Models\Schedule;
use App\Models\Shift;

/**
 * Authorization for a Schedule's Shifts (#356, PRD #352, ADR-0021 §2) — the Shift
 * authoring write seam. A Shift has no audience of its own for *management*: it inherits
 * its whole context from its Schedule, so every authoring ability answers to the same
 * schedule-admin gate the {@see SchedulePolicy} uses — the owning Group must run
 * scheduling, and the actor must be able to act as its Scheduler (Chair-implication
 * folded in by {@see Member::canActAs()}).
 *
 * There is no read ability here: reading a Shift is reading its Schedule, gated by the
 * SchedulePolicy. The super-tier short-circuit lives in a single `Gate::before`
 * (AppServiceProvider) and is never re-checked here.
 */
class ShiftPolicy
{
    /**
     * Who may add a Shift to a Schedule: a schedule admin of the Schedule's owning
     * Group. Adding a Shift is purely additive — it disturbs no Sign-up — so it is
     * permitted even on a `published` Schedule (ADR-0021 §2), with no state guard.
     */
    public function create(Member $actor, Schedule $schedule): bool
    {
        return $this->administersSchedulingFor($actor, $schedule->group);
    }

    /**
     * Who may edit a Shift — its times, capacity, kind or audience: a schedule admin of
     * the owning Group. Authority never leaks across Groups. Lowering capacity below the
     * current Sign-up count is blocked once Sign-ups exist (#357); the guard is the admin
     * gate today.
     */
    public function update(Member $actor, Shift $shift): bool
    {
        return $this->administersSchedulingFor($actor, $shift->schedule->group);
    }

    /**
     * Who may delete a Shift: a schedule admin of the owning Group. Deletion is
     * cancelling, and is permitted only at zero Sign-ups (ADR-0021 §2) — cancelling is
     * never silent. The zero-Sign-up predicate lands with #357; the guard is the admin
     * gate today.
     */
    public function delete(Member $actor, Shift $shift): bool
    {
        return $this->administersSchedulingFor($actor, $shift->schedule->group);
    }

    /**
     * The schedule-admin gate, identical to the SchedulePolicy's: the Group runs
     * scheduling *and* the actor can act as its Scheduler (Chair-implication folded in by
     * {@see Member::canActAs()}). The capability guard matters because Chair-implication
     * would otherwise grant schedule powers on a non-scheduling Group the member chairs.
     */
    private function administersSchedulingFor(Member $actor, Group $group): bool
    {
        return $group->has_scheduling
            && $actor->canActAs(Role::Scheduler, $group);
    }
}
