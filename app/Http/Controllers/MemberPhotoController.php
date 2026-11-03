<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateMemberPhotoRequest;
use App\Models\Member;
use App\Support\ProfilePhotoStorage;
use Illuminate\Http\RedirectResponse;

/**
 * The Records Photo action (#922, ADR-0038 §3), as the Legacy app's Photo dialog: upload or
 * replace any Member's photo through the profile photo storage, and set the photo-public flag.
 */
class MemberPhotoController extends Controller
{
    public function __invoke(UpdateMemberPhotoRequest $request, Member $member, ProfilePhotoStorage $photos): RedirectResponse
    {
        $member->photo_public = $request->boolean('photo_public');

        $previousPath = null;

        if ($request->hasFile('photo')) {
            // Stored before the save, deleted after it, so a failed save never strands the Member
            // without a photo (as Settings → Profile).
            $previousPath = $member->getAttributes()['photo_path'] ?? null;
            $member->photo_path = $photos->store($request->file('photo'));
        }

        if ($member->isDirty()) {
            $member->updated_by = $request->user()->id;
        }

        $member->save();

        if ($previousPath !== null) {
            $photos->delete($previousPath);
        }

        return back();
    }
}
