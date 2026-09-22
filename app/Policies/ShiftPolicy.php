<?php

namespace App\Policies;

use App\Enums\Role;
use App\Enums\ScheduleState;
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
     * current Sign-up count is a state rule enforced in {@see UpdateShiftRequest}, not an
     * authority question; this answers only who may edit at all.
     */
    public function update(Member $actor, Shift $shift): bool
    {
        return $this->administersSchedulingFor($actor, $shift->schedule->group);
    }

    /**
     * Who may delete a Shift: a schedule admin of the owning Group, and only at zero
     * Sign-ups (ADR-0021 §2) — deletion is cancelling, and cancelling is never silent, so a
     * Shift with Members on it must be emptied first.
     */
    public function delete(Member $actor, Shift $shift): bool
    {
        return $this->administersSchedulingFor($actor, $shift->schedule->group)
            && ! $shift->signUps()->exists();
    }

    /**
     * Who may bulk-delete Shifts on a Schedule (#362, ADR-0021 §2): a schedule admin of
     * the owning Group. This gates the batch as a whole — bulk is not a privileged path,
     * so it answers to the same admin gate as a single write. The per-row zero-Sign-ups
     * rule is not an authority question and is applied row by row in the controller (a
     * Shift with Members on it is skipped and reported), so it does not belong here.
     */
    public function deleteAny(Member $actor, Schedule $schedule): bool
    {
        return $this->administersSchedulingFor($actor, $schedule->group);
    }

    /**
     * Who may author their own Shift on a Schedule in a self-serve Group (#585, ADR-0026 §1)
     * — the Member write the Scheduler-only {@see create} above is not. Distinct from a
     * Scheduler adding a slot: this is a Member writing a record about themselves, and it
     * creates their Sign-up in the same action. Four gates, all met:
     *
     * - the owning Group is **self-serve** (`self_serve_shifts` — off everywhere but GI);
     * - the Schedule is **published** (a draft is the Scheduler's workshop, never a
     *   Member's canvas) and the actor may **read** it (you cannot write onto a Schedule
     *   you cannot see);
     * - the actor clears the **DMV-wide floor** ({@see Category::canSignUp()}); and
     * - the actor holds a **membership** of the Group whose per-Group standing permits it
     *   ({@see MembershipStatus::canSignUp()}) — the same two floors that gate taking a seat,
     *   because authoring one is taking one.
     *
     * Ownership of what they write is derived, never stored (#334); it is answered by
     * {@see manageSelfServe} on the way back out.
     */
    public function createSelfServe(Member $actor, Schedule $schedule): bool
    {
        $group = $schedule->group;

        if (! $group->self_serve_shifts || $schedule->state !== ScheduleState::Published) {
            return false;
        }

        if (! $actor->can('view', $schedule) || ! $actor->category->canSignUp()) {
            return false;
        }

        $membership = $actor->membershipIn($group);

        return $membership !== null && $membership->status->canSignUp();
    }

    /**
     * Who may change or delete a self-authored Shift (#585, ADR-0026 §1) — the derived
     * ownership rule, since no column records who wrote a row (#334 stays open). A Member
     * owns a Shift, and may edit or delete it, exactly while:
     *
     * - the owning Group is **self-serve**;
     * - the Shift's **capacity is 1** (a Member never authors a wider slot, so a wider one
     *   is a Scheduler's and off-limits);
     * - the Shift's **only Sign-up is the actor's** (they hold the one seat, so the Shift is
     *   theirs); and
     * - the Shift **has not started** — edit and delete close at the start, the same bound
     *   take and drop answer to (#554, ADR-0026 §6). After the start only the Scheduler acts.
     *
     * The accepted edge (ADR-0026 §1): a capacity-1 Shift a Scheduler authored and placed
     * this Member on is editable by them too, because ownership is derived, not authored.
     */
    public function manageSelfServe(Member $actor, Shift $shift): bool
    {
        // Read the seats from the loaded relation when the caller already has them (the Agenda
        // payload loads every Shift's Sign-ups), and query once when it does not (a route-bound
        // Shift in a Form Request) — so this never lazy-loads under strict mode nor N+1s a page.
        $shift->loadMissing('signUps');
        $signUps = $shift->signUps;

        return $shift->schedule->group->self_serve_shifts
            && $shift->capacity === 1
            && $signUps->count() === 1
            && $signUps->first()->member_id === $actor->getKey()
            && ! $shift->hasStarted();
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
