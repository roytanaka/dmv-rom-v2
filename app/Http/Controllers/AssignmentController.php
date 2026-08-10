<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAssignmentRequest;
use App\Models\Shift;
use Illuminate\Http\RedirectResponse;

/**
 * Officer assignment write seam (#359, PRD #352, ADR-0021 §Sign-up) — a Scheduler placing a
 * named Member on a Shift. This is a *distinct actor* from the self-service Sign-up on
 * {@see SignUpController} (a Scheduler seating someone else, not the authenticated user
 * seating themselves), so it is a separate seam — but it writes the **same** ordinary Sign-up
 * row: there is no second entity and no distinguishing column (ADR-0021 §Sign-up, point 2 of
 * #334). Removal is not here — a Scheduler clears a placed seat through the ordinary drop seam
 * ({@see SignUpController::destroy}), whose ownership-gated email keeps an officer removal
 * silent.
 *
 * The Form Request has already cleared the schedule-admin gate, both sign-up floors on the
 * placed Member, capacity, and the one-seat rule, so this is a single insert. No email fires:
 * the app does not tell a Scheduler what she just did.
 */
class AssignmentController extends Controller
{
    /**
     * Place the named Member on the route-bound Shift, writing an ordinary Sign-up row. The
     * Shift comes from the route; the seated Member is the validated `member_id`.
     */
    public function store(StoreAssignmentRequest $request, Shift $shift): RedirectResponse
    {
        $shift->signUps()->create(['member_id' => $request->integer('member_id')]);

        return back();
    }
}
