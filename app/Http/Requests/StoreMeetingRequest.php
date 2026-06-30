<?php

namespace App\Http\Requests;

use App\Enums\MeetingLinkKind;
use App\Models\Group;
use App\Models\Meeting;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Adding a meeting to a Group (#193, PRD #186). The mutation is authorized
 * structurally here — `authorize()` resolves the route-bound Group and delegates to
 * the MeetingPolicy, never returning `true` blindly — and `rules()` is a whitelist
 * of exactly the meeting fields plus its labelled links. The controller passes
 * `validated()`, never `all()`.
 */
class StoreMeetingRequest extends FormRequest
{
    /**
     * Authorize against the MeetingPolicy: the actor must be able to add a meeting
     * to the route-bound Group (Secretary / Chair / super-tier, meetings on).
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', [Meeting::class, $this->route('group')]);
    }

    /**
     * The whitelist of fields a meeting may set. `title` / `description` /
     * `location` are member-authored content; `video_url` is a plain external link;
     * `is_published` is the drafting flag (defaults published at the DB). `links`
     * is the conventional agenda / minutes / report set — each a labelled URL, with
     * at most one of each kind.
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
