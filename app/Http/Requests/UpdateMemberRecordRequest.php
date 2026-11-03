<?php

namespace App\Http\Requests;

use App\Models\FeeCategory;
use App\Models\Member;
use App\Models\ServiceAward;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A member admin's save of the Member record (#917, ADR-0038 §2, §3). The whitelist is every
 * field the Records office edits. Category and status date move only through a Category history
 * entry; `notes` is absent because the log only grows, through `new_note`.
 */
class UpdateMemberRecordRequest extends FormRequest
{
    private const YEAR = ['nullable', 'integer', 'between:1900,2100'];

    private const LINE = ['nullable', 'string', 'max:255'];

    private const TEXT = ['nullable', 'string', 'max:65535'];

    private function target(): Member
    {
        return $this->route('member');
    }

    public function authorize(): bool
    {
        return $this->user()->can('administer', $this->target());
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'string', 'lowercase', 'email', 'max:255', Rule::unique(Member::class)->ignore($this->target()->id)],
            'phone' => self::LINE,
            'alternate_phone' => self::LINE,
            'business_phone' => self::LINE,
            'address_street' => self::LINE,
            'address_city' => self::LINE,
            'address_province' => self::LINE,
            'address_postal_code' => self::LINE,
            'address_country' => self::LINE,
            'membership_number' => self::LINE,
            'membership_expiry' => ['nullable', 'date'],
            'membership_lifetime' => ['nullable', 'boolean'],
            'amount_paid' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'paid_through_year' => self::YEAR,
            'fee_category' => ['nullable', 'string', Rule::exists(FeeCategory::class, 'code')],
            'entry_year' => self::YEAR,
            'service_awards' => ['nullable', 'array'],
            'service_awards.*' => ['string', 'distinct', Rule::exists(ServiceAward::class, 'code')],
            'last_award_year' => self::YEAR,
            'next_award_year' => self::YEAR,
            'other_awards' => self::LINE,
            'emergency_contact' => self::LINE,
            'emergency_contact_number' => self::LINE,
            'newsletter_opt_in' => ['nullable', 'boolean'],
            'code_of_conduct_year' => self::YEAR,
            'renewal_year' => self::YEAR,
            'renewal_status' => self::LINE,
            'renewal_placements' => self::TEXT,
            'renewal_other' => self::TEXT,
            'record_form_placements' => self::TEXT,
            'record_form_other' => self::TEXT,
            'mentor_name' => self::LINE,
            'volunteer_experience' => self::TEXT,
            'past_experience' => self::TEXT,
            'education' => self::TEXT,
            'new_note' => self::TEXT,
        ];
    }
}
