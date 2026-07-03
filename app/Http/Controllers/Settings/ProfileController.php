<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use App\Support\ProfilePhotoStorage;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Show the user's profile settings page.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('settings/Profile', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => $request->session()->get('status'),
            // Fine-grained, per-resource UI hint (ADR-0017 §9): may this member edit
            // this record? The server still enforces the action; this only drives
            // whether the form's controls render enabled.
            'can' => [
                'update' => $request->user()->can('update', $request->user()),
            ],
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request, ProfilePhotoStorage $photos): RedirectResponse
    {
        $member = $request->user();

        // `photo` is a file, not a column — fill the text fields, then set photo_path
        // from the processed upload separately. current_password only re-authenticates.
        $member->fill($request->safe()->except('current_password', 'photo'));

        if ($member->isDirty('email')) {
            $member->email_verified_at = null;
        }

        $previousPath = null;

        if ($request->hasFile('photo')) {
            // Stash before storing so a failed save never leaves the member without a photo.
            // Read the raw attribute, not $member->photo_path: under strict mode an
            // unhydrated column (e.g. an actingAs/factory model) would throw (see photoUrl).
            $previousPath = $member->getAttributes()['photo_path'] ?? null;
            $member->photo_path = $photos->store($request->file('photo'));
        }

        $member->save();

        if ($previousPath !== null) {
            $photos->delete($previousPath);
        }

        return to_route('settings.profile');
    }

    /**
     * Remove the member's profile photo entirely (#234): unlink the stored file — not
     * just null the column — so no picture lingers fetchable under its UUID URL, then
     * clear `photo_path` so rendering reverts to initials everywhere the member appears.
     */
    public function destroyPhoto(Request $request, ProfilePhotoStorage $photos): RedirectResponse
    {
        $member = $request->user();

        abort_unless($member->can('update', $member), 403);

        // Raw read for the same strict-mode reason as update() above.
        $photos->delete($member->getAttributes()['photo_path'] ?? null);
        $member->photo_path = null;
        $member->save();

        return to_route('settings.profile');
    }
}
