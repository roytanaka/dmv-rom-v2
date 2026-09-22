<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReorderObjectsRequest;
use App\Http\Requests\StoreObjectRequest;
use App\Http\Requests\UpdateObjectRequest;
use App\Models\Group;
use App\Models\HandlingObject;
use Illuminate\Http\RedirectResponse;

/**
 * Maintaining a Group's Objects (#584, ADR-0026 §3) — the Scheduling section's Objects block:
 * add, rename, retire, reinstate and reorder the handling collection a Gallery Interpreter takes
 * onto the floor, edited by a Scheduler or Chair. The endpoints mirror the shift-kind ones of
 * #567 one for one. Every mutation is structurally authorized in its Form Request, which
 * delegates to the SchedulePolicy's `manageObjects` gate. There is no delete: an Object is
 * retired and reinstated, never removed, so the Sign-ups already reserving it keep their name.
 */
class ObjectController extends Controller
{
    /**
     * Add an Object to the route-bound Group. The name comes from the Form Request whitelist; the
     * new Object is active and lands at the end of the sort order, so it is offered last in the
     * picker until the admin reorders. Scoped to the Group's own Objects — no other Group's are
     * read.
     */
    public function store(StoreObjectRequest $request, Group $group): RedirectResponse
    {
        $group->objects()->create([
            'name' => $request->validated('name'),
            'active' => true,
            'sort_order' => ($group->objects()->max('sort_order') ?? -1) + 1,
        ]);

        return back();
    }

    /**
     * Rename an Object, or retire / reinstate it. The fields come from the Form Request
     * whitelist: a rename sends `name`, a retire sends `active` false, a reinstate `active` true.
     * Existing Sign-ups keep their link, so a rename shows through and a retired Object still
     * names its old Sign-ups.
     */
    public function update(UpdateObjectRequest $request, HandlingObject $object): RedirectResponse
    {
        $object->update($request->validated());

        return back();
    }

    /**
     * Reorder the route-bound Group's Objects to match the submitted id list. Each id's position
     * in the list becomes its `sort_order`, which sets the picker order. The Form Request has
     * confirmed every id is one of this Group's Objects, so the update never touches another
     * Group's Objects.
     */
    public function reorder(ReorderObjectsRequest $request, Group $group): RedirectResponse
    {
        foreach ($request->validated('ids') as $position => $id) {
            $group->objects()->whereKey($id)->update(['sort_order' => $position]);
        }

        return back();
    }
}
