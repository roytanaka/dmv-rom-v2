<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReorderShiftKindsRequest;
use App\Http\Requests\StoreShiftKindRequest;
use App\Http\Requests\UpdateShiftKindRequest;
use App\Models\Group;
use App\Models\ShiftKind;
use Illuminate\Http\RedirectResponse;

/**
 * Maintaining a Group's shift kinds (#567, ADR-0021 §3) — the Scheduling section's shift-kind
 * block: add, rename, retire, reinstate and reorder the small per-Group vocabulary of kinds,
 * edited by a Scheduler or Chair. Every mutation is structurally authorized in its Form
 * Request, which delegates to the SchedulePolicy's `manageShiftKinds` gate. There is no
 * delete: legacy never removes a kind, it retires and reinstates one, so the Shifts already
 * carrying a kind keep their name.
 */
class ShiftKindController extends Controller
{
    /**
     * Add a kind to the route-bound Group. The name comes from the Form Request whitelist; the
     * new kind is active and lands at the end of the sort order, so it is offered last in the
     * picker until the admin reorders. Scoped to the Group's own kinds — no other Group's kinds
     * are read.
     */
    public function store(StoreShiftKindRequest $request, Group $group): RedirectResponse
    {
        $group->shiftKinds()->create([
            'name' => $request->validated('name'),
            'active' => true,
            'sort_order' => ($group->shiftKinds()->max('sort_order') ?? -1) + 1,
        ]);

        return back();
    }

    /**
     * Rename a kind, or retire / reinstate it. The fields come from the Form Request whitelist:
     * a rename sends `name`, a retire sends `active` false, a reinstate `active` true. Existing
     * Shifts keep their `shift_kind_id`, so a rename shows through and a retired kind still
     * labels its old Shifts.
     */
    public function update(UpdateShiftKindRequest $request, ShiftKind $shiftKind): RedirectResponse
    {
        $shiftKind->update($request->validated());

        return back();
    }

    /**
     * Reorder the route-bound Group's kinds to match the submitted id list. Each id's position
     * in the list becomes its `sort_order`, which sets the picker order. The Form Request has
     * confirmed every id is one of this Group's kinds, so the update never touches another
     * Group's kinds.
     */
    public function reorder(ReorderShiftKindsRequest $request, Group $group): RedirectResponse
    {
        foreach ($request->validated('ids') as $position => $id) {
            $group->shiftKinds()->whereKey($id)->update(['sort_order' => $position]);
        }

        return back();
    }
}
