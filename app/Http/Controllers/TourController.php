<?php

namespace App\Http\Controllers;

use App\Http\Requests\DeleteTourRequest;
use App\Http\Requests\ReorderToursRequest;
use App\Http\Requests\StoreTourRequest;
use App\Http\Requests\UpdateTourRequest;
use App\Http\Requests\UpdateTourShiftKindsRequest;
use App\Models\Group;
use App\Models\Tour;
use Illuminate\Http\RedirectResponse;

/**
 * Maintaining a Group's Tour list (#788, ADR-0033 §1, §4) — the Settings tab's Tours card: add,
 * rename, retire, restore, reorder, set open to all, map onto shift kinds, and delete one added
 * in error. Every mutation is authorized in its Form Request through the TourPolicy's `manage`
 * gate.
 */
class TourController extends Controller
{
    /**
     * Add a Tour to the Group, active and last in the order.
     */
    public function store(StoreTourRequest $request, Group $group): RedirectResponse
    {
        $group->tours()->create([
            'name' => $request->validated('name'),
            'active' => true,
            'sort_order' => ($group->tours()->max('sort_order') ?? -1) + 1,
        ]);

        return back();
    }

    /**
     * Rename, retire, restore, or set open to all.
     */
    public function update(UpdateTourRequest $request, Tour $tour): RedirectResponse
    {
        $tour->update($request->validated());

        return back();
    }

    /**
     * Reorder the Group's Tours to match the submitted id list.
     */
    public function reorder(ReorderToursRequest $request, Group $group): RedirectResponse
    {
        foreach ($request->validated('ids') as $position => $id) {
            $group->tours()->whereKey($id)->update(['sort_order' => $position]);
        }

        return back();
    }

    /**
     * Replace the shift kinds the Tour maps to.
     */
    public function updateShiftKinds(UpdateTourShiftKindsRequest $request, Tour $tour): RedirectResponse
    {
        $tour->shiftKinds()->sync($request->validated('shift_kinds'));

        return back();
    }

    /**
     * Delete a Tour nothing points at. The pivot rows cascade.
     */
    public function destroy(DeleteTourRequest $request, Tour $tour): RedirectResponse
    {
        $tour->delete();

        return back();
    }
}
