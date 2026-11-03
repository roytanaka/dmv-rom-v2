<?php

namespace App\Http\Controllers;

use App\Http\Requests\DeleteMemberLanguageRequest;
use App\Http\Requests\StoreMemberLanguageRequest;
use App\Http\Requests\UpdateMemberLanguageRequest;
use App\Models\Member;
use App\Models\MemberLanguage;
use Illuminate\Http\RedirectResponse;

/**
 * The languages on a Member record (#918, ADR-0038 §3): add, change and remove, each counting as
 * an edit of the record.
 */
class MemberLanguageController extends Controller
{
    public function store(StoreMemberLanguageRequest $request, Member $member): RedirectResponse
    {
        $member->languages()->create($request->validated());
        $member->markUpdatedBy($request->user());

        return back();
    }

    public function update(UpdateMemberLanguageRequest $request, Member $member, MemberLanguage $language): RedirectResponse
    {
        $language->update($request->validated());
        $member->markUpdatedBy($request->user());

        return back();
    }

    public function destroy(DeleteMemberLanguageRequest $request, Member $member, MemberLanguage $language): RedirectResponse
    {
        $language->delete();
        $member->markUpdatedBy($request->user());

        return back();
    }
}
