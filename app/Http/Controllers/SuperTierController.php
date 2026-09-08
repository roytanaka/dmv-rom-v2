<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateSuperTierRequest;
use App\Models\Member;
use Illuminate\Http\RedirectResponse;

/**
 * The dedicated, separately-gated path that grants or revokes super-tier — the one
 * org-wide grant (#153, ADR-0017 §1). super-tier is never a field on any member
 * form: it is not mass-assignable, so this controller sets the attribute directly
 * off the request's validated boolean. Authorization lives in the Form Request.
 */
class SuperTierController extends Controller
{
    public function __invoke(UpdateSuperTierRequest $request, Member $member): RedirectResponse
    {
        $member->super_tier = $request->boolean('super_tier');
        $member->save();

        return back();
    }
}
