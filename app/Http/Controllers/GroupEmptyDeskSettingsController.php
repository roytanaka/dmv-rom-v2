<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateEmptyDeskSettingsRequest;
use App\Models\Group;
use Illuminate\Http\RedirectResponse;

/**
 * The Group's empty-desk settings write seam (#487, spec #479, ADR-0024 §7) — the Scheduling
 * section's empty-desk block: the alert on/off, the look-ahead days, and which shift kinds to
 * watch, edited by a Scheduler or Chair. A dedicated endpoint, parallel to
 * {@see GroupReminderSettingsController}, structurally authorized in its Form Request (the
 * SchedulePolicy `updateEmptyDeskAlert` gate). The `can` hint on the page is a UI hint only — the
 * server enforces here regardless.
 */
class GroupEmptyDeskSettingsController extends Controller
{
    /**
     * Save the alert switch and look-ahead on the route-bound Group, then set the watch flag on
     * its shift kinds to match the submitted list — the kinds on it are watched, every other kind
     * of the Group is cleared. The two Group fields come from the Form Request whitelist; the
     * watch flag is toggled by an Eloquent update scoped to the Group's own kinds, so no kind of
     * another Group is ever touched.
     */
    public function update(UpdateEmptyDeskSettingsRequest $request, Group $group): RedirectResponse
    {
        $group->update($request->safe()->only(['empty_desk_alert_enabled', 'empty_desk_days_ahead']));

        $watched = $request->validated('watched_shift_kinds');

        $group->shiftKinds()->whereIn('id', $watched)->update(['alert_when_empty' => true]);
        $group->shiftKinds()->whereNotIn('id', $watched)->update(['alert_when_empty' => false]);

        return back();
    }
}
