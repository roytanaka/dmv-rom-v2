<?php

namespace App\Http\Requests;

use App\Models\Group;
use App\Support\Stewardships\StewardshipGuard;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Archive or unarchive a Group from DMV Settings (#845, ADR-0036 §2). No fields: the route
 * says which way. Every refusal lands on an `archive` error, shown under the button.
 *
 * Authorized on the Group-less `administer-groups` ability only. Never a Group-scoped
 * ability: GroupWrites' Gate::before refuses those on an archived Group, super-tier included,
 * which would leave an archived Group with no way back.
 */
class ArchiveGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('administer-groups');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }

    /**
     * Refuse the root and Containers, which have no settings here. Refuse archiving a Group
     * that holds a stewardship: an archived holder would leave its authority stranded, so the
     * error names each one it holds (ADR-0036 §2).
     *
     * @return array<int, Closure(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $group = $this->group();

            if (! $group->isStructurallyEditable()) {
                $validator->errors()->add('archive', __('dmv_settings.error.not_editable'));

                return;
            }

            $refusal = $this->routeIs('dmv-settings.groups.archive') ? StewardshipGuard::refusal($group) : null;
            if ($refusal !== null) {
                $validator->errors()->add('archive', $refusal);
            }
        }];
    }

    private function group(): Group
    {
        return $this->route('group');
    }
}
