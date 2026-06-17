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
     * The member directory: the living roster as a single load-all payload (#169).
     * Scoped to {@see Member::scopeInDirectory()} — departed and not-yet-activated
     * members are excluded in one place. Eager-loads each member's Groups + roles
     * for the Groups column, then routes through
     * {@see MemberResource::directoryCollection()}, which suppresses contact PII for
     * every row regardless of viewer (contact is a profile-only concern).
     */
    public function index(): Response
    {
        $members = Member::inDirectory()
            ->with('memberships.group', 'memberships.roles')
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        return Inertia::render('members/Index', [
            // resolve() to the unwrapped array of rows — the page consumes a flat
            // `members` list, not a `{ data: [...] }` envelope.
            'members' => MemberResource::directoryCollection($members)->resolve(),
        ]);
    }

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
