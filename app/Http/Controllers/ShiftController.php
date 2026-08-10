<?php

namespace App\Http\Controllers;

use App\Http\Requests\DeleteShiftRequest;
use App\Http\Requests\StoreShiftRequest;
use App\Http\Requests\UpdateShiftRequest;
use App\Models\Schedule;
use App\Models\Shift;
use Illuminate\Http\RedirectResponse;

/**
 * Shift authoring (#356, PRD #352, ADR-0021 §2) — the Scheduler's write seam for the
 * Shifts on a Schedule, parallel to the Schedule write seam on
 * {@see ScheduleController}. Every mutation is structurally authorized in its Form
 * Request, which delegates to the ShiftPolicy — the same schedule-admin gate the
 * SchedulePolicy uses (a Scheduler or Chair of the owning Group, plus the super-tier,
 * only while the Group's scheduling capability is on).
 *
 * The rules make bad states unreachable rather than unwound: a Shift's times are held
 * inside its Schedule's date range, and deletion is cancelling — never silent.
 */
class ShiftController extends Controller
{
    /**
     * Add a Shift to a Schedule. The owning Schedule comes from the route; the fields
     * come from the Form Request whitelist. Adding is purely additive, so it is
     * permitted even on a `published` Schedule (ADR-0021 §2). `capacity` and `audience`
     * fall to the database defaults (1, `group`) when absent.
     */
    public function store(StoreShiftRequest $request, Schedule $schedule): RedirectResponse
    {
        $schedule->shifts()->create($request->validated());

        return back();
    }

    /**
     * Edit a Shift — its times, capacity, kind or audience. Raising capacity is a single
     * update that disturbs nothing; edited times stay inside the Schedule's range. The
     * owning Schedule is fixed at creation.
     */
    public function update(UpdateShiftRequest $request, Shift $shift): RedirectResponse
    {
        $shift->update($request->validated());

        return back();
    }

    /**
     * Delete a Shift — cancelling it. Permitted only at zero Sign-ups once Sign-ups
     * exist (#357); cancelling is never silent.
     */
    public function destroy(DeleteShiftRequest $request, Shift $shift): RedirectResponse
    {
        $shift->delete();

        return back();
    }
}
