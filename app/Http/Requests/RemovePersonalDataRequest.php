<?php

namespace App\Http\Requests;

use App\Actions\RemovePersonalData;
use App\Models\Member;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Run Remove personal data (#921, ADR-0038 §6), through `MemberPolicy::removePersonalData`. The
 * member admin types the total the preview counted, Members and Applicants (ADR-0038 §6); the count
 * is taken again here, so a stale preview cannot confirm a different run.
 */
class RemovePersonalDataRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('removePersonalData', Member::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'year' => ['required', 'integer', 'min:1900', 'max:2100'],
            'confirm_count' => ['required', 'integer', 'min:0'],
        ];
    }

    /**
     * @return list<callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $count = app(RemovePersonalData::class)->total((int) $this->input('year'));

                if ((int) $this->input('confirm_count') !== $count) {
                    $validator->errors()->add('confirm_count', __('records.remove_personal_data.count_mismatch'));
                }
            },
        ];
    }
}
