<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateTourRulesRequest;
use App\Models\Group;
use App\Support\Tours\QualificationRules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

/**
 * The Group's status-rule settings (#793, ADR-0033 §7) — the Settings tab's Tour rules card: the
 * trainee Tour, the starter Tours and whether LOA removes qualifications. Authorized in its Form
 * Request (the TourPolicy `manageRules` gate). The rules themselves run in
 * {@see QualificationRules} when a standing changes.
 */
class GroupTourRulesController extends Controller
{
    /**
     * Save the three settings. The starter flag is replaced wholesale: the listed Tours become
     * starters, every other Tour of the Group stops being one.
     */
    public function update(UpdateTourRulesRequest $request, Group $group): RedirectResponse
    {
        $data = $request->validated();

        DB::transaction(function () use ($group, $data): void {
            $group->update([
                'trainee_tour_id' => $data['trainee_tour_id'],
                'loa_removes_qualifications' => $data['loa_removes_qualifications'],
            ]);

            $group->tours()->update(['starter' => false]);
            $group->tours()->whereIn('id', $data['starter_tour_ids'])->update(['starter' => true]);
        });

        return back();
    }
}
