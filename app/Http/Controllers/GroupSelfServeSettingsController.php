<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateSelfServeSettingsRequest;
use App\Models\Group;
use Illuminate\Http\RedirectResponse;

/**
 * The Group's self-serve settings write seam (#582, spec #576, ADR-0026 §1 and §2) — the
 * Scheduling section's self-serve card: self-serve shifts on/off and the unit length in minutes,
 * edited by a Scheduler or Chair. A dedicated endpoint, parallel to
 * {@see GroupEmptyDeskSettingsController}, structurally authorized in its Form Request (the
 * SchedulePolicy `updateSelfServe` gate). The `can` hint on the page is a UI hint only — the
 * server enforces here regardless.
 */
class GroupSelfServeSettingsController extends Controller
{
    /**
     * Save the two self-serve settings on the route-bound Group. The fields come from the Form
     * Request whitelist; nothing else on the Group is touched.
     */
    public function update(UpdateSelfServeSettingsRequest $request, Group $group): RedirectResponse
    {
        $group->update($request->validated());

        return back();
    }
}
