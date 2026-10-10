<?php

namespace App\Http\Requests\Concerns;

use App\Models\Member;
use App\Models\Shift;
use Illuminate\Validation\Rule;

/**
 * The Tour a Sign-up records (#790, ADR-0033 §2, §6), for the write seams that seat a Member on a
 * Shift. On a kind that maps to Tours the field is **required** and must be one of the Tours the
 * Member may give ({@see Member::toursGivableOn()}); on any other Shift it is prohibited, so the
 * Sign-up stays Tour-less. A one-Tour kind fills the Tour in when the body leaves it out.
 *
 * The server checks it, not the browser (ADR-0033 §6 deviation from legacy).
 */
trait ChoosesTour
{
    /**
     * Fill in the Tour on a one-Tour kind (#790) when the body carries none, so a take on a
     * single-Tour slot needs no dialog. Call from `prepareForValidation()`.
     */
    protected function fillOnlyTour(Shift $shift): void
    {
        $offered = $shift->toursOffered();

        if ($offered->count() === 1 && $this->input('tour_id') === null) {
            $this->merge(['tour_id' => $offered->first()->id]);
        }
    }

    /**
     * The `tour_id` field rules for the Member's Sign-up on the Shift.
     *
     * @return array<string, array<int, mixed>>
     */
    protected function tourRules(Shift $shift, Member $member): array
    {
        if ($shift->toursOffered()->isEmpty()) {
            return ['tour_id' => ['prohibited']];
        }

        return [
            'tour_id' => ['required', 'integer', Rule::in($member->toursGivableOn($shift)->pluck('id')->all())],
        ];
    }

    /**
     * The `tour_id` field rules for a Scheduler's write (#791, ADR-0033 §6): placing a Member or
     * changing a seat's Tour. No qualification check, since a Scheduler may place anyone. On a
     * Tour kind the field may be blank or any of the kind's active Tours; elsewhere it is
     * prohibited.
     *
     * @return array<string, array<int, mixed>>
     */
    protected function officerTourRules(Shift $shift): array
    {
        $offered = $shift->toursOffered();

        if ($offered->isEmpty()) {
            return ['tour_id' => ['prohibited']];
        }

        return [
            'tour_id' => ['nullable', 'integer', Rule::in($offered->pluck('id')->all())],
        ];
    }

    /**
     * Plain messages for the `tour_id` rules: a missing Tour asks for one, a Tour the Member may
     * not give says so, and a Tour on a Tour-less Shift is refused.
     *
     * @return array<string, string>
     */
    protected function tourMessages(): array
    {
        return [
            'tour_id.required' => trans('group.scheduling_panel.tour.required'),
            'tour_id.integer' => trans('group.scheduling_panel.tour.not_givable'),
            'tour_id.in' => trans('group.scheduling_panel.tour.not_givable'),
            'tour_id.prohibited' => trans('group.scheduling_panel.tour.none_here'),
        ];
    }
}
