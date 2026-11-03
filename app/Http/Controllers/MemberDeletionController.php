<?php

namespace App\Http\Controllers;

use App\Actions\DeleteMember;
use App\Http\Requests\DeleteMemberRequest;
use App\Models\Member;
use Illuminate\Http\RedirectResponse;

/**
 * Delete on the Past, Deceased and Admin lists (#921, ADR-0038 §6), through {@see DeleteMember}.
 */
class MemberDeletionController extends Controller
{
    public function __invoke(DeleteMemberRequest $request, Member $member, DeleteMember $deleteMember): RedirectResponse
    {
        $deleteMember->delete($member, $request->user());

        return back();
    }
}
