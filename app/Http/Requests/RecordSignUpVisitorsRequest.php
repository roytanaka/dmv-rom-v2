<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

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
 *
 * GDR's five origins (#448, ADR-0023 §3) — `visitors_france_europe`, `visitors_quebec`,
 * `visitors_toronto`, `visitors_rest_of_canada`, `visitors_other_countries` — are the one place
 * a Group collects where its visitors came from. **All five are required together** where the
 * Group collects them (four of five is a refusal, not a partial save), each a whole number at or
 * above zero, and **the five must sum to `visitor_count`** — legacy enforced this in a JavaScript
 * alert only; here it is a server rule ({@see withValidator}) whose message names both totals.
 * Every other Group — GDR is the only one — **rejects** a provenance value rather than storing it.
 */
class RecordSignUpVisitorsRequest extends FormRequest
{
    /**
     * The five origin fields, in the order GDR's sign-out panel lists them. Named once so the
     * whitelist, the prohibition on other Groups, and the sum check all read the same list.
     *
     * @var list<string>
     */
    private const PROVENANCE_FIELDS = [
        'visitors_france_europe',
        'visitors_quebec',
        'visitors_toronto',
        'visitors_rest_of_canada',
        'visitors_other_countries',
    ];

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

        // GDR's five origins are required together where the Group collects them — four of five
        // fails on the missing field — and prohibited everywhere else. The sum rule is a separate
        // pass ({@see withValidator}) so it runs only once the five are known to be whole numbers.
        $provenanceRules = $group->collects_visitor_provenance
            ? ['required', 'integer', 'min:0']
            : ['prohibited'];

        return [
            'visitor_count' => $group->collects_visitor_count
                ? ['required', 'integer', 'min:0']
                : ['prohibited'],
            // The tour-leading second box: optional where the count is required, and refused
            // outright on any Group that does not collect the split — a count-only Group included.
            'extra_interaction_count' => $group->collects_extra_interactions
                ? ['nullable', 'integer', 'min:0']
                : ['prohibited'],
            ...array_fill_keys(self::PROVENANCE_FIELDS, $provenanceRules),
        ];
    }

    /**
     * GDR's sum rule (#448, ADR-0023 §3): the five origins must add up to the visitor count.
     * Legacy enforced it in a browser alert only; here the server refuses the write when they
     * disagree. Runs only once the basic rules have passed, so every value is known to be a whole
     * number — a decimal or a missing field fails on its own rule first and never reaches here.
     * The error hangs on the first origin field (where the panel shows it) and names both totals.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $group = $this->route('signUp')->shift->schedule->group;

            if (! $group->collects_visitor_provenance || $validator->errors()->isNotEmpty()) {
                return;
            }

            $sum = array_sum(array_map(fn (string $field): int => (int) $this->input($field), self::PROVENANCE_FIELDS));
            $count = (int) $this->input('visitor_count');

            if ($sum !== $count) {
                $validator->errors()->add(
                    self::PROVENANCE_FIELDS[0],
                    trans('group.scheduling_panel.agenda.sign_out.provenance_sum', ['sum' => $sum, 'count' => $count]),
                );
            }
        });
    }

    /**
     * Say plainly that a whole number is expected for a decimal, and that the count cannot be
     * negative, so a Member corrects the entry rather than guessing why the form refused it. The
     * five origins share one whole-number, one not-negative and one required message — the origin
     * they name is already the box the error hangs on, so a per-field wording would only repeat it.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        $messages = [
            'visitor_count.integer' => trans('group.scheduling_panel.agenda.sign_out.whole_number'),
            'visitor_count.min' => trans('group.scheduling_panel.agenda.sign_out.not_negative'),
            'extra_interaction_count.integer' => trans('group.scheduling_panel.agenda.sign_out.extra_whole_number'),
            'extra_interaction_count.min' => trans('group.scheduling_panel.agenda.sign_out.extra_not_negative'),
        ];

        foreach (self::PROVENANCE_FIELDS as $field) {
            $messages["{$field}.required"] = trans('group.scheduling_panel.agenda.sign_out.provenance_required');
            $messages["{$field}.integer"] = trans('group.scheduling_panel.agenda.sign_out.provenance_whole_number');
            $messages["{$field}.min"] = trans('group.scheduling_panel.agenda.sign_out.provenance_not_negative');
        }

        return $messages;
    }
}
