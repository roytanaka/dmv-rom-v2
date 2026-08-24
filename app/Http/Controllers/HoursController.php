<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreHoursRecordRequest;
use App\Models\Group;
use App\Models\HoursRecord;
use Illuminate\Http\RedirectResponse;

/**
 * Extra-hours entry (#408, PRD #406, ADR-0022 §2) — the write seam parallel to the Hours
 * tab's read surface on {@see GroupController}. The mutation is structurally authorized in
 * {@see StoreHoursRecordRequest}, which delegates to the HoursRecordPolicy.
 *
 * The one non-negotiable deviation from legacy (ADR-0022 §4): the hours are always written
 * for the authenticated Member. `$request->user()` is both the whose-hours and the author;
 * a `member_id` in the request body never reaches the write, because the model method takes
 * the Member object, not an id from input.
 */
class HoursController extends Controller
{
    /**
     * Add extra hours for the authenticated Member on a Group, in one of the two open
     * months. The write is additive and floors at zero; a blank or zero entry (or a
     * correction the floor swallows) writes nothing at all. Every real write appends an
     * Hours adjustment in the same transaction ({@see HoursRecord::enterExtra()}).
     */
    public function store(StoreHoursRecordRequest $request, Group $group): RedirectResponse
    {
        $actor = $request->user();

        HoursRecord::enterExtra(
            $actor,
            $group,
            $request->validated('year_month'),
            (int) $request->validated('hours'),
            $actor,
        );

        return back();
    }
}
