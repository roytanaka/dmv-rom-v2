<?php

namespace App\Http\Requests;

use App\Actions\DeleteMember;
use App\Models\Member;
use App\Policies\MemberPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Delete a Member (#921, ADR-0038 §6), through `MemberPolicy::delete`. The member admin types the
 * Member's last name; a Member {@see DeleteMember} refuses is a validation error on `member`.
 */
class DeleteMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        // The list check is repeated outside the Gate: the super-tier's Gate::before would pass
        // `delete` for a live Member.
        return $this->user()->can('delete', $this->member())
            && MemberPolicy::deletable($this->member());
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'confirm_last_name' => ['required', 'string'],
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

                if (trim((string) $this->input('confirm_last_name')) !== trim((string) $this->member()->last_name)) {
                    $validator->errors()->add('confirm_last_name', __('records.delete.last_name_mismatch'));

                    return;
                }

                if (($reason = app(DeleteMember::class)->refusal($this->member())) !== null) {
                    $validator->errors()->add('member', __($reason));
                }
            },
        ];
    }

    private function member(): Member
    {
        return $this->route('member');
    }
}
