<?php

namespace App\Policies;

use App\Enums\Category;
use App\Enums\MembershipStatus;
use App\Enums\Role;
use App\Enums\ShiftAudience;
use App\Http\Controllers\SignUpController;
use App\Http\Requests\StoreSignUpRequest;
use App\Models\Group;
use App\Models\Member;
use App\Models\Shift;
use App\Models\SignUp;

/**
 * Authorization for a Shift's Sign-ups (#357, PRD #352, ADR-0021 §Sign-up) — the taking
 * and dropping seam. This is where the two floors and the one field meet: `create`
 * answers *"may this person take this Shift"*, `delete` answers *"may this person drop
 * this seat"*.
 *
 * **Who may take a Shift is two floors and one field.** The floors answer "may this
 * person work at all"; the `audience` answers "who sees a Sign-up button":
 *
 * 1. **DMV-wide floor** — {@see Category::canSignUp()} on the actor's standing,
 *    reused unchanged. `Loa` / `Withdrawn` / `Resigned` / `Deceased` are barred; the rest,
 *    including Provisional and PreActive, may sign up (signing up is how a trainee trains).
 * 2. **Per-Group floor** — {@see MembershipStatus::canSignUp()} on the actor's
 *    membership in the owning Group. A Member in good DMV standing may still be paused in
 *    one Group without touching another.
 * 3. **`audience`** — `group` (default) offers the seat only to Members of the owning
 *    Group; `open` offers it to any Member who can read the Schedule, wherever they belong.
 *    Cross-Group participation is a query, never a relationship.
 *
 * The super-tier short-circuit lives in the single `Gate::before` (AppServiceProvider) and
 * is never re-checked here. Capacity and the one-seat-per-Member rule are state, not
 * authority, and are validated in {@see StoreSignUpRequest}.
 */
class SignUpPolicy
{
    /**
     * Who may take a seat on a Shift. The actor must be able to read the Schedule the
     * Shift sits on (you cannot take what you cannot see), clear the DMV-wide floor, and
     * then satisfy the `audience`:
     *
     * - `group` — be a Member of the owning Group whose per-Group standing permits it.
     * - `open` — anyone who cleared the read and DMV-wide floors; a Member of the owning
     *   Group is still held to their per-Group standing there (a paused Member cannot take
     *   even their own Group's open Shift), while a Member of another Group has no per-Group
     *   floor to answer to and is admitted by the read check alone.
     */
    public function create(Member $actor, Shift $shift): bool
    {
        $schedule = $shift->schedule;

        if (! $actor->can('view', $schedule)) {
            return false;
        }

        if (! $actor->category->canSignUp()) {
            return false;
        }

        $membership = $actor->membershipIn($schedule->group);

        if ($shift->audience === ShiftAudience::Group) {
            return $membership !== null && $membership->status->canSignUp();
        }

        // `open`: a Member of the owning Group still answers to their per-Group floor
        // there; a Member of another Group has none and passes on the read check above.
        return $membership === null || $membership->status->canSignUp();
    }

    /**
     * Who may place a named Member on a Shift (officer assignment, #359) — the Scheduler's
     * write, distinct from a Member's self-service {@see create}. Two actors are in play: the
     * *actor* is authorised by the schedule-admin gate (Scheduler or Chair of the owning
     * Group, capability-guarded — the same gate the {@see ShiftPolicy} uses), and the *placed
     * Member* must clear both sign-up floors, exactly as a self-service Member would:
     *
     * 1. **DMV-wide floor** — {@see Category::canSignUp()} on the placed Member's standing.
     * 2. **Per-Group floor** — {@see MembershipStatus::canSignUp()} on the placed Member's
     *    membership in the owning Group, so a person on leave or resigned cannot be placed.
     *
     * The Shift's `audience` is deliberately **not** consulted: the discovery filter answers
     * "who is shown a Sign-up button", and placing a person the Scheduler has already spoken
     * to is exactly the case it is not for (ADR-0021 §Sign-up). Capacity and the one-seat rule
     * are state, not authority, and are validated in {@see StoreAssignmentRequest}.
     */
    public function assign(Member $actor, Shift $shift, Member $member): bool
    {
        if (! $this->administersSchedulingFor($actor, $shift->schedule->group)) {
            return false;
        }

        if (! $member->category->canSignUp()) {
            return false;
        }

        $membership = $member->membershipIn($shift->schedule->group);

        return $membership !== null && $membership->status->canSignUp();
    }

    /**
     * Who may drop a seat: the Member who holds it, or a schedule admin of the owning Group
     * (officer removal, #359) — so a Scheduler can clear any Sign-up on her Group's Shifts,
     * whoever created it, and a regular who stops coming is not stranded. Cancel has no
     * deadline — a Member may drop for as long as they could have taken it (ADR-0021 follows
     * live legacy, whose `+2 days` guard was commented out with "allow cancel ANY TIME") — so
     * there is no temporal guard here. The email that a self-drop fires is gated on ownership
     * in {@see SignUpController::destroy}, so an officer removal stays
     * silent even though it reuses this ability.
     */
    public function delete(Member $actor, SignUp $signUp): bool
    {
        return $signUp->member_id === $actor->getKey()
            || $this->administersSchedulingFor($actor, $signUp->shift->schedule->group);
    }

    /**
     * The schedule-admin gate, identical to the SchedulePolicy's and ShiftPolicy's: the
     * Group runs scheduling *and* the actor can act as its Scheduler (Chair-implication
     * folded in by {@see Member::canActAs()}). The capability guard matters because
     * Chair-implication would otherwise grant schedule powers on a non-scheduling Group the
     * member chairs.
     */
    private function administersSchedulingFor(Member $actor, Group $group): bool
    {
        return $group->has_scheduling
            && $actor->canActAs(Role::Scheduler, $group);
    }
}
