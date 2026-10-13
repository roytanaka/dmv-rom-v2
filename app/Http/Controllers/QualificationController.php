<?php

namespace App\Http\Controllers;

use App\Http\Requests\DeleteQualificationRequest;
use App\Http\Requests\StoreQualificationRequest;
use App\Http\Requests\UpdateQualificationRequest;
use App\Models\Group;
use App\Models\Qualification;
use Illuminate\Http\RedirectResponse;

/**
 * Keeping a Group's qualifications (#789, ADR-0033 §3, §4) — the by-Tour and by-Member screens'
 * add, change-date and remove. Every mutation is authorized in its Form Request through the
 * TourPolicy's `manage` gate.
 */
class QualificationController extends Controller
{
    /**
     * Qualify a Member on a Tour. One row per pair: an existing row, inactive or not, is made
     * active with the new Last vet date rather than duplicated.
     */
    public function store(StoreQualificationRequest $request, Group $group): RedirectResponse
    {
        Qualification::query()->updateOrCreate(
            [
                'group_member_id' => $request->validated('group_member_id'),
                'tour_id' => $request->validated('tour_id'),
            ],
            [
                'active' => true,
                'last_vet_date' => $request->validated('last_vet_date'),
            ],
        );

        return back();
    }

    /**
     * Change the Last vet date.
     */
    public function update(UpdateQualificationRequest $request, Qualification $qualification): RedirectResponse
    {
        $qualification->update($request->validated());

        return back();
    }

    /**
     * Remove the qualification: the Member no longer gives the Tour. Status rules (#793) make
     * rows inactive instead; this is the officer's explicit removal.
     */
    public function destroy(DeleteQualificationRequest $request, Qualification $qualification): RedirectResponse
    {
        $qualification->delete();

        return back();
    }
}
