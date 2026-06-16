<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateMemberRequest;
use App\Models\Member;
use Illuminate\Http\RedirectResponse;

class MemberController extends Controller
{
    /**
     * Update a member's record. Authorization and the field whitelist both live in
     * the Form Request (ADR-0017); the controller only ever sees `validated()`
     * data, never the raw request.
     */
    public function update(UpdateMemberRequest $request, Member $member): RedirectResponse
    {
        $member->fill($request->validated());

        if ($member->isDirty('email')) {
            $member->email_verified_at = null;
        }

        $member->save();

        return back();
    }
}
