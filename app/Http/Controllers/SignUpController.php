<?php

namespace App\Http\Controllers;

use App\Http\Requests\DeleteSignUpRequest;
use App\Http\Requests\RecordSignUpVisitorsRequest;
use App\Http\Requests\StoreSignUpRequest;
use App\Http\Requests\SubstituteSignUpRequest;
use App\Http\Requests\UpdateSignUpTourRequest;
use App\Models\Member;
use App\Models\Shift;
use App\Models\SignUp;
use App\Support\Notices\SignUpCancellationNoticeWriter;
use App\Support\Notices\SignUpSubstitutionNoticeWriter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

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
        // The Tour the taker gives (#790, ADR-0033 §2) — null on a kind with no Tours.
        $signUp = $shift->signUps()->create([
            'member_id' => $request->user()->getKey(),
            'tour_id' => $request->validated('tour_id'),
        ]);

        // The Objects the taker is reserving on this seat (#586, ADR-0026 §3) — absent on a Group
        // with no Objects; the Form Request has refused a retired or double-booked one.
        $signUp->objects()->sync($request->validated('objects') ?? []);

        return back();
    }

    /**
     * Drop a Shift — cancelling the Sign-up. Permitted at any time up to (and past) the
     * Shift's start; the guard is ownership, resolved in the Form Request.
     *
     * A Member cancelling is **the single event where otherwise nobody finds out until the
     * shift is empty**, so every Scheduler and Chair of the owning Group is told —
     * unconditionally, with no threshold or proximity window (#358, ADR-0021 §Sign-up
     * "Notification"). The Notice fires only for a Member dropping their *own* seat: a
     * Scheduler removing a placed Member (officer removal, #359) is a distinct actor who is
     * already in contact with the person, and that silence is deliberate. Guarding on
     * ownership here keeps that silence true now that officer removal reuses this seam.
     *
     * Nothing sends in the request (#481, ADR-0024). The {@see SignUpCancellationNoticeWriter}
     * writes one Notice Delivery per recipient and returns; the every-minute Drain sends them.
     * The Sign-up is gone by drain time, so each row carries a payload snapshot of everything
     * the mail names — the dropped Member, the Group, and the Shift's Schedule, times, and kind.
     */
    public function destroy(DeleteSignUpRequest $request, SignUp $signUp, SignUpCancellationNoticeWriter $notices): RedirectResponse
    {
        $isSelfCancellation = $signUp->member_id === $request->user()->getKey();

        $signUp->loadMissing('shift.kind', 'shift.schedule.group', 'member');
        $shift = $signUp->shift;
        $member = $signUp->member;

        $signUp->delete();

        // A Member cancelling their own seat tells every Scheduler and Chair; a Scheduler
        // removing a placed Member (officer removal, #359) stays silent — they are already in
        // contact with the person. The Notice is written from one place ({@see
        // SignUpCancellationNoticeWriter}) shared with the self-serve Shift delete (ADR-0026 §1).
        if ($isSelfCancellation) {
            $notices->write($shift, $member);
        }

        return back();
    }

    /**
     * Record the after-the-shift numbers on a Sign-up (#445, #652, PRD #651, ADR-0023 §5). One
     * seam for the whole feature: the Post-shift report on the shift card, in the Agenda and in My
     * sign-ups, and an Officer's correction, all PATCH here. The Form Request has already resolved
     * the policy (the seat-holder's own seat inside the window, or any seat for a schedule admin)
     * and whitelisted the fields — whose seat is written comes from the route binding, never the
     * body — so this fills and saves.
     *
     * The saved Sign-up's id is flashed (#668), with the ones the page already keeps, so the next
     * page load keeps each Shift in My sign-ups with its summary, even though a first save means
     * it no longer owes a number. Two saves in a row keep both.
     */
    public function record(RecordSignUpVisitorsRequest $request, SignUp $signUp): RedirectResponse
    {
        $signUp->record($request->validated(), $request->user());

        return back()->with(SignUp::JUST_SAVED_FLASH, [...$request->session()->get(SignUp::SHOWN_SAVED_KEY, []), $signUp->id]);
    }

    /**
     * Change the Tour on a Sign-up (#791, ADR-0033 §6): the seat-holder until the Shift starts,
     * or a schedule admin any time. The Form Request has resolved who may and which Tour.
     */
    public function updateTour(UpdateSignUpTourRequest $request, SignUp $signUp): RedirectResponse
    {
        $signUp->update(['tour_id' => $request->validated('tour_id')]);

        return back();
    }

    /**
     * The Members the holder may hand their Booking seat to (#798, ADR-0032 §8), for the
     * Substitute picker: id and full name. The holder alone, until the Shift starts.
     */
    public function substitutes(Request $request, SignUp $signUp): JsonResponse
    {
        abort_unless($request->user()->can('substitute', $signUp), 403);

        return response()->json([
            'members' => $signUp->eligibleSubstitutes()
                ->map(fn (Member $member): array => ['id' => $member->id, 'name' => $member->fullName()])
                ->all(),
        ]);
    }

    /**
     * Hand a seat on a Booking to a substitute (#798, ADR-0032 §8, §9). A Booking seat cannot be
     * dropped; the holder names a Member who takes it instead. The seat moves in one write, keeping
     * its Tour and Objects. The old and new Member and the Group's Bookers are told through the
     * Delivery queue ({@see SignUpSubstitutionNoticeWriter}); no cancellation Notice fires.
     */
    public function substitute(SubstituteSignUpRequest $request, SignUp $signUp, SignUpSubstitutionNoticeWriter $notices): RedirectResponse
    {
        $signUp->loadMissing('member', 'shift.kind', 'shift.booking.tour', 'shift.schedule.group');
        $previous = $signUp->member;

        $signUp->update(['member_id' => $request->integer('member_id')]);

        $notices->write($signUp->shift, $previous, $signUp->member()->sole());

        return back();
    }
}
