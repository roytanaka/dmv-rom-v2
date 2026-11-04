<?php

namespace App\Http\Requests\Renewal;

use App\Actions\SaveRenewalForm;
use App\Support\Renewal\RenewalPlacements;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Step 2 of the Renewal (#940, ADR-0038 §9): the renewal form. The Legacy app's `checkRenewalForm`:
 * both confirm boxes, an expiry (a date or Life) unless the Member chose LOA or Resign, a Category,
 * and at least one placement unless LOA, Resign or Sustaining. A past expiry warns on the page and
 * saves. `placements` are the indexes of the ticked checklist rows.
 */
class StoreRenewalFormRequest extends RenewalRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        $form = app(SaveRenewalForm::class);
        $rows = count($form->checklist($this->user()));
        $groups = $form->groups()->pluck('id')->all();

        return [
            'membership_number' => ['required', 'string', 'max:255'],
            'membership_number_confirmed' => ['accepted'],
            'membership_lifetime' => ['boolean'],
            'membership_expiry' => [
                Rule::requiredIf(fn () => ! $this->boolean('membership_lifetime') && ! in_array($this->input('category'), ['LOA', SaveRenewalForm::RESIGN], true)),
                'nullable', 'date',
            ],
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'address_street' => ['nullable', 'string', 'max:255'],
            'address_city' => ['nullable', 'string', 'max:255'],
            'address_province' => ['nullable', 'string', 'max:255'],
            'address_postal_code' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'alternate_phone' => ['nullable', 'string', 'max:255'],
            'business_phone' => ['nullable', 'string', 'max:255'],
            'placements' => ['array'],
            'placements.*' => ['integer', 'min:0', 'max:'.max($rows - 1, 0), 'distinct'],
            'other' => ['array', 'max:2'],
            'other.*.group_id' => ['nullable', 'integer', Rule::in($groups)],
            'other.*.position' => ['nullable', Rule::in(RenewalPlacements::POSITIONS)],
            'other_texts' => ['array', 'max:2'],
            'other_texts.*' => ['nullable', 'string', 'max:255'],
            'emergency_contact' => ['nullable', 'string', 'max:240'],
            'emergency_contact_number' => ['nullable', 'string', 'max:255'],
            'emergency_verified' => ['accepted'],
            'category' => ['required', Rule::in($form->categoryCodes())],
        ];
    }

    /** @return list<callable> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty() || in_array($this->input('category'), SaveRenewalForm::NO_PLACEMENT_NEEDED, true)) {
                return;
            }

            $named = filled($this->input('placements'))
                || collect($this->input('other', []))->contains(fn ($row): bool => filled($row['group_id'] ?? null))
                || collect($this->input('other_texts', []))->contains(fn ($text): bool => filled(trim((string) $text)));

            if (! $named) {
                $validator->errors()->add('placements', __('renewal.form.placement_required'));
            }
        }];
    }
}
