<?php

namespace App\Http\Requests;

use App\Models\Member;
use App\Rules\ProfilePhoto;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The Records Photo action (#922, ADR-0038 §3): a member admin uploads or replaces a Member's
 * photo and sets whether peers see it. The file is optional, so the flag can change alone.
 */
class UpdateMemberPhotoRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Member $member */
        $member = $this->route('member');

        return $this->user()->can('administer', $member);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'photo' => ['nullable', 'file', new ProfilePhoto],
            'photo_public' => ['required', 'boolean'],
        ];
    }
}
