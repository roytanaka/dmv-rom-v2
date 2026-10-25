<?php

namespace App\Http\Requests;

use App\Http\Controllers\DmvSettingsController;
use App\Models\Group;
use App\Models\GroupStewardship;
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

            if (! DmvSettingsController::editable($group)) {
                $validator->errors()->add('archive', __('dmv_settings.error.not_editable'));

                return;
            }

            $held = $this->routeIs('dmv-settings.groups.archive') ? $group->stewardships()->get() : collect();
            if ($held->isNotEmpty()) {
                $validator->errors()->add('archive', __('dmv_settings.error.archive_steward', [
                    'group' => $group->name,
                    'stewardship' => $held
                        ->map(fn (GroupStewardship $row) => __("dmv_settings.stewardship.{$row->function->value}.title"))
                        ->join(', ', __('dmv_settings.archive.and')),
                ]));
            }
        }];
    }

    private function group(): Group
    {
        return $this->route('group');
    }
}
