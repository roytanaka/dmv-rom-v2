<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateMemberRequest;
use App\Http\Resources\MemberResource;
use App\Models\Member;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MemberController extends Controller
{
    /**
     * Show a member record. The payload routes through the centralized
     * MemberResource (ADR-0017), whose allowlist gates contact PII behind the
     * `viewContact` ability — the controller never hand-builds a member array.
     *
     * Eager-loads the relations the resource and `viewContact` traverse: the
     * target's Groups + roles for the payload, and the viewer's roles so
     * `canActAs` resolves in memory rather than per-call.
     */
    public function show(Request $request, Member $member): Response
    {
        $member->load('memberships.group', 'memberships.roles');
        $request->user()->loadMissing('memberships.roles');

        return Inertia::render('members/Show', [
            'member' => new MemberResource($member),
        ]);
    }

    /**
     * Update a member's record. Authorization and the field whitelist both live in
     * the Form Request (ADR-0017).
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
