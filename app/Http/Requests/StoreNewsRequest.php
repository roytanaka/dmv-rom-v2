<?php

namespace App\Http\Requests;

use App\Models\Group;
use App\Models\News;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Posting a news item (#155, ADR-0017 §4–5). The mutation is authorized
 * structurally here — `authorize()` resolves the posting Group from the request
 * and delegates to the NewsPolicy, never returning `true` blindly — and `rules()`
 * is a whitelist of exactly the content fields. The controller passes
 * `validated()`, never `all()`.
 */
class StoreNewsRequest extends FormRequest
{
    /**
     * Resolve the Group the item would be posted on behalf of, or null when the
     * request names no valid Group.
     */
    private function postingGroup(): ?Group
    {
        return Group::find($this->input('posting_group_id'));
    }

    /**
     * Authorize against the NewsPolicy: the actor must be able to act as
     * news-editor of the named announcements-on Group. A missing or unknown Group
     * fails closed.
     */
    public function authorize(): bool
    {
        $group = $this->postingGroup();

        return $group !== null
            && $this->user()->can('create', [News::class, $group]);
    }

    /**
     * The whitelist of fields a post may set. `posting_group_id` names the
     * publishing Group; `title` / `body` are as-authored content.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'posting_group_id' => ['required', 'integer', 'exists:groups,id'],
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
        ];
    }
}
