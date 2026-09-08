<?php

namespace App\Http\Controllers;

use App\Http\Requests\DeleteScheduleRequest;
use App\Http\Requests\StoreScheduleRequest;
use App\Http\Requests\UpdateScheduleRequest;
use App\Models\Group;
use App\Models\Schedule;
use Illuminate\Http\RedirectResponse;

/**
 * Group scheduling authoring (#354, PRD #352, ADR-0021 §1) — the Scheduler's write
 * seam for a Group's Schedules, parallel to the read surface on
 * {@see GroupController::showSchedule}. Every mutation is structurally authorized in
 * its Form Request, which delegates to the SchedulePolicy (a Scheduler or Chair of the
 * Group, plus the super-tier, and only while the Group's scheduling capability is on).
 * The `can` props on the Scheduling tab are UI hints only — the server enforces here
 * regardless.
 */
class ScheduleController extends Controller
{
    /**
     * Add a Schedule to a Group. The owning Group comes from the route; the fields
     * come from the Form Request whitelist. A new Schedule starts as a `draft` (the
     * database default) — publication is a later edit, not a creation choice.
     */
    public function store(StoreScheduleRequest $request, Group $group): RedirectResponse
    {
        $group->schedules()->create($request->validated());

        return back();
    }

    /**
     * Edit a Schedule — its authored fields and its publication `state`. Publishing
     * and un-publishing arrive here too, as a `state` change the Form Request has
     * already guarded. The owning Group is fixed at creation.
     */
    public function update(UpdateScheduleRequest $request, Schedule $schedule): RedirectResponse
    {
        $schedule->update($request->validated());

        return back();
    }

    /**
     * Delete a Schedule. Permitted only at zero Sign-ups once Sign-ups exist (#357);
     * a Schedule with history is permanent.
     */
    public function destroy(DeleteScheduleRequest $request, Schedule $schedule): RedirectResponse
    {
        $schedule->delete();

        return back();
    }
}
