<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateNoEmailFlagRequest;
use App\Models\Member;
use Illuminate\Http\RedirectResponse;

/**
 * The dedicated, separately-gated path that sets or clears a Member's no-email flag
 * (#483, ADR-0024 §9). Like super-tier, the flag is never a field on any member form:
 * it is not mass-assignable, so this controller sets the attribute directly off the
 * request's validated boolean. Authorization lives in the Form Request.
 */
class NoEmailFlagController extends Controller
{
    public function __invoke(UpdateNoEmailFlagRequest $request, Member $member): RedirectResponse
    {
        $member->no_email = $request->boolean('no_email');
        $member->save();

        return back();
    }
}
