<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Open the Stewardships section (#846, ADR-0036 §7), optionally with a draft to preview in the
 * query string: `?member_admin=ID&org_mail[]=ID`. Super-tier only, through `administer-groups`.
 * A query string drops an empty array, so an absent `org_mail` beside a `member_admin` is a
 * draft of no org-wide senders.
 */
class PreviewStewardshipsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('administer-groups') ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'member_admin' => ['nullable', 'integer'],
            'org_mail' => ['array'],
            'org_mail.*' => ['integer'],
        ];
    }

    /** Whether the query carries a draft; without one there is nothing to preview. */
    public function hasDraft(): bool
    {
        return $this->hasAny(['member_admin', 'org_mail']);
    }

    /** The draft member-admin holder; null keeps the saved one. */
    public function draftMemberAdmin(): ?int
    {
        $id = $this->validated('member_admin');

        return $id === null ? null : (int) $id;
    }

    /**
     * The draft org-wide sender holders.
     *
     * @return list<int>
     */
    public function draftOrgMail(): array
    {
        return array_values(array_map('intval', (array) $this->validated('org_mail', [])));
    }
}
