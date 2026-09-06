<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Recording the after-the-shift numbers on a Sign-up (#445, PRD #443, ADR-0023 §5). The whole
 * write rule lives here, in the shape of {@see StoreHoursRecordRequest}: `authorize()` resolves
 * the route-bound Sign-up and delegates to the SignUpPolicy — it never returns `true` — and
 * `rules()` is a whitelist of exactly the fields a seat records.
 *
 * **Whose seat is written comes from the route binding, never from the request body.** There is
 * no member id and no sign-up id in the whitelist: a `member_id` naming someone else is ignored
 * because it is not in `validated()`, so a request cannot file for another Member by posting an
 * id. This is the named deviation from legacy, whose endpoint takes a row id from POST and
 * updates it with no ownership check.
 *
 * `visitor_count` is **required** when the owning Group collects it — the 96-98% rule, and it is
 * the server's, not the disabled button's — a whole number (a decimal is refused with a message
 * that says so, not silently truncated) and at or above zero (a negative can never make a
 * Group's total go down). A Group that collects nothing has the field **rejected**, not ignored,
 * so a stray value never lands in a column nobody reads.
 *
 * `extra_interaction_count` is the tour-leading second box (#447, ADR-0023 §2) — visitors served
 * outside the tour. It is **optional where the visitor count is required**: a tour with no extra
 * interactions is a real zero the volunteer may leave blank, and the Sign Out button does not
 * wait for it. Same whole-number, non-negative shape. Only a Group that collects the split accepts
 * it; every other Group — including one that collects the count but not the split — **rejects** a
 * value rather than folding it into the count or dropping it silently.
 */
class RecordSignUpVisitorsRequest extends FormRequest
{
    /**
     * Authorize against the SignUpPolicy: the actor must be able to record on the route-bound
     * Sign-up — their own seat, from five minutes before the Shift ends onward.
     */
    public function authorize(): bool
    {
        return $this->user()->can('record', $this->route('signUp'));
    }

    /**
     * The whitelist of fields a sign-out records. `visitor_count` is required where the Group
     * collects it and prohibited where it does not; `extra_interaction_count` is optional where
     * the Group collects the split and prohibited where it does not. Either way each is a whole
     * number at or above zero. No member id and no sign-up id — the seat comes from the route,
     * never the body.
     *
     * @return array<string, array<int, ValidationRule|string>>
     */
    public function rules(): array
    {
        $group = $this->route('signUp')->shift->schedule->group;

        return [
            'visitor_count' => $group->collects_visitor_count
                ? ['required', 'integer', 'min:0']
                : ['prohibited'],
            // The tour-leading second box: optional where the count is required, and refused
            // outright on any Group that does not collect the split — a count-only Group included.
            'extra_interaction_count' => $group->collects_extra_interactions
                ? ['nullable', 'integer', 'min:0']
                : ['prohibited'],
        ];
    }

    /**
     * Say plainly that a whole number is expected for a decimal, and that the count cannot be
     * negative, so a Member corrects the entry rather than guessing why the form refused it.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'visitor_count.integer' => trans('group.scheduling_panel.agenda.sign_out.whole_number'),
            'visitor_count.min' => trans('group.scheduling_panel.agenda.sign_out.not_negative'),
            'extra_interaction_count.integer' => trans('group.scheduling_panel.agenda.sign_out.extra_whole_number'),
            'extra_interaction_count.min' => trans('group.scheduling_panel.agenda.sign_out.extra_not_negative'),
        ];
    }
}
