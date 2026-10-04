<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateReminderSettingsRequest;
use App\Models\Group;
use Illuminate\Http\RedirectResponse;

/**
 * The Group's Reminder settings write seam (#486, PRD #479, ADR-0024 §7) — the Scheduling
 * section's Reminders block: on/off and lead days, edited by a Scheduler or Chair. A dedicated
 * endpoint, parallel to the ScheduleController authoring seams, structurally authorized in its
 * Form Request (the SchedulePolicy `updateReminders` gate). The `can` hint on the page is a UI
 * hint only — the server enforces here regardless.
 */
class GroupReminderSettingsController extends Controller
{
    /**
     * Save the two Reminder settings on the route-bound Group. The fields come from the Form
     * Request whitelist; nothing else on the Group is touched.
     */
    public function update(UpdateReminderSettingsRequest $request, Group $group): RedirectResponse
    {
        $group->update($request->validated());

        return back();
    }
}
