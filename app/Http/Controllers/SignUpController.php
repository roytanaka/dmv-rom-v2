<?php

namespace App\Http\Controllers;

use App\Http\Requests\DeleteSignUpRequest;
use App\Http\Requests\StoreSignUpRequest;
use App\Mail\SignUpCancelled;
use App\Models\Shift;
use App\Models\SignUp;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Mail;

/**
 * Sign-up write seam (#357, PRD #352, ADR-0021 §Sign-up) — a Member taking a Shift and
 * dropping it, from the same place. Both mutations are structurally authorized in their Form
 * Request, which delegates to the SignUpPolicy (the two floors and the Shift's `audience` on
 * the way in; owning the seat on the way out). A Scheduler *placing* a named Member is a
 * separate seam ({@see AssignmentController}, #359); a Scheduler *removing* one reuses
 * {@see destroy} below — the SignUpPolicy's `delete` also admits a schedule admin — and the
 * ownership-gated email keeps that officer removal silent.
 *
 * A self-service Sign-up records nothing about who created it — there is no provenance column
 * (point 2 of #334) — so the controller seats the authenticated user and nothing more.
 */
class SignUpController extends Controller
{
    /**
     * Take a Shift. The Shift comes from the route; the seat is the authenticated Member.
     * The Form Request has already cleared the floors, the `audience`, capacity, and the
     * one-seat rule, so this is a single insert.
     */
    public function store(StoreSignUpRequest $request, Shift $shift): RedirectResponse
    {
        $shift->signUps()->create(['member_id' => $request->user()->getKey()]);

        return back();
    }

    /**
     * Drop a Shift — cancelling the Sign-up. Permitted at any time up to (and past) the
     * Shift's start; the guard is ownership, resolved in the Form Request.
     *
     * A Member cancelling is **the single event where otherwise nobody finds out until the
     * shift is empty**, so every Scheduler of the owning Group is emailed — unconditionally,
     * with no threshold or proximity window (#358, ADR-0021 §Sign-up "Notification"). The mail
     * fires only for a Member dropping their *own* seat: a Scheduler removing a placed Member
     * (officer removal, #359) is a distinct actor who is already in contact with the person,
     * and that silence is deliberate. Guarding on ownership here keeps that silence true now
     * that officer removal reuses this seam.
     */
    public function destroy(DeleteSignUpRequest $request, SignUp $signUp): RedirectResponse
    {
        $isSelfCancellation = $signUp->member_id === $request->user()->getKey();

        $signUp->loadMissing('shift.kind', 'shift.schedule.group', 'member');
        $shift = $signUp->shift;
        $member = $signUp->member;

        $signUp->delete();

        if ($isSelfCancellation) {
            foreach ($shift->schedule->group->schedulers() as $scheduler) {
                Mail::to($scheduler)->send(new SignUpCancelled($shift, $member));
            }
        }

        return back();
    }
}
