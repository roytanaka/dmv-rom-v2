<?php

namespace App\Http\Requests;

use App\Models\HoursRecord;
use App\Support\OrgTime;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Entering extra hours on a Group (#408, PRD #406, ADR-0022 §2, §4). The mutation is
 * authorized structurally here — `authorize()` resolves the route-bound Group and delegates
 * to the HoursRecordPolicy, never returning `true` blindly — and `rules()` is a whitelist of
 * exactly the two fields a Member enters: which month, and the whole-number delta.
 *
 * The whitelist deliberately carries **no member id**: whose hours are written is the
 * authenticated session, never anything the request names (ADR-0022 §4, the security
 * deviation from legacy). The controller writes for `Auth::user()`; a `member_id` in the
 * body is ignored because it is not in `validated()`.
 *
 * `year_month` must be one of the two months entry is open for — the current month and the
 * previous one on the org wall clock ({@see OrgTime::entryMonths()}) — so there is no way to
 * reach any earlier month. `hours` is a whole number: legacy rejects decimals outright
 * ("we are not concerned with minutes"), so a decimal is refused with a message that says
 * so rather than being silently truncated. It is nullable and may be negative — a blank
 * writes nothing (§16) and a negative corrects an overstatement (§14).
 */
class StoreHoursRecordRequest extends FormRequest
{
    /**
     * Authorize against the HoursRecordPolicy: the actor's Category must still participate
     * and they must be able to open the route-bound Group.
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', [HoursRecord::class, $this->route('group')]);
    }

    /**
     * The whitelist of fields an extra-hours entry may set. `year_month` is constrained to
     * the two-month window; `hours` is a whole number (nullable for a no-op blank, signed
     * for a correction). No member id — the writer is the session, not the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'year_month' => ['required', 'string', Rule::in(OrgTime::entryMonths())],
            'hours' => ['nullable', 'integer'],
        ];
    }

    /**
     * Say plainly that whole hours are expected when a decimal is entered, so a Member
     * rounds rather than guessing why the form refused them (ADR-0022 §2, story 17).
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'hours.integer' => trans('hours.entry.whole_hours'),
        ];
    }
}
