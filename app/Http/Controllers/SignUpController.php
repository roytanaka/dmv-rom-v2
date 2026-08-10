<?php

namespace App\Http\Controllers;

use App\Http\Requests\DeleteSignUpRequest;
use App\Http\Requests\StoreSignUpRequest;
use App\Models\Shift;
use App\Models\SignUp;
use Illuminate\Http\RedirectResponse;

/**
 * Sign-up write seam (#357, PRD #352, ADR-0021 §Sign-up) — a Member taking a Shift and
 * dropping it, from the same place. Both mutations are structurally authorized in their Form
 * Request, which delegates to the SignUpPolicy (the two floors and the Shift's `audience` on
 * the way in; owning the seat on the way out). A Scheduler placing or removing a named Member
 * is a separate seam that lands with officer assignment (#359).
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
     */
    public function destroy(DeleteSignUpRequest $request, SignUp $signUp): RedirectResponse
    {
        $signUp->delete();

        return back();
    }
}
