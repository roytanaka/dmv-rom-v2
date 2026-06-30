<?php

namespace App\Http\Requests;

use App\Enums\MeetingLinkKind;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Editing a meeting (#193, PRD #186) — its fields, its links, and the
 * published/hidden toggle. Authorized structurally against the MeetingPolicy
 * (an officer of the owning Group) and validated to the same whitelist as creation.
 * The owning Group is fixed at creation, so it is not editable here. The controller
 * passes `validated()`, never `all()`.
 */
class UpdateMeetingRequest extends FormRequest
{
    /**
     * Authorize against the MeetingPolicy: the actor must be able to update the
     * route-bound meeting (an officer of its Group, or the super-tier).
     */
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('meeting'));
    }

    /**
     * The whitelist of editable fields — the same shape as creation, minus the
     * fixed owning Group. `links` is synced wholesale: the supplied set replaces the
     * meeting's current links.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:10000'],
            'held_at' => ['required', 'date'],
            'location' => ['nullable', 'string', 'max:255'],
            'video_url' => ['nullable', 'url', 'max:2048'],
            'is_published' => ['sometimes', 'boolean'],
            'links' => ['sometimes', 'array'],
            'links.*.kind' => ['required', Rule::enum(MeetingLinkKind::class), 'distinct'],
            'links.*.url' => ['required', 'url', 'max:2048'],
        ];
    }
}
