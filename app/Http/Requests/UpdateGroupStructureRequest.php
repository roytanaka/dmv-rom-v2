<?php

namespace App\Http\Requests;

use App\Enums\GroupLogo;
use App\Enums\Kind;
use App\Enums\LifecycleState;
use App\Enums\ListingVisibility;
use App\Enums\Role;
use App\Http\Controllers\DmvSettingsController;
use App\Models\Group;
use App\Models\GroupMemberRole;
use App\Models\GroupStewardship;
use Carbon\Carbon;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Save a Group's structure from DMV Settings (#843, ADR-0036 §5). The whitelist is the
 * structure form: name, parent, kind, listing, logo, dates, the capability and collection
 * flags and the hours multiplier. The slug is never accepted, so a crafted one is dropped.
 *
 * Authorized on the Group-less `administer-groups` ability only. Never a Group-scoped
 * ability: GroupWrites' Gate::before refuses those on an archived Group, super-tier included.
 * The Group itself is refused in `after()` instead: the root and Containers have no form, and
 * an archived Group's form is read-only until it is unarchived (#845).
 */
class UpdateGroupStructureRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('administer-groups');
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $group = $this->group();

        return [
            'name' => ['required', 'string', 'max:255'],
            'parent_id' => ['required', 'integer', function (string $attribute, mixed $value, Closure $fail) use ($group): void {
                if ((int) $value === $group->id || $group->descendants()->contains('id', $value)) {
                    $fail('dmv_settings.error.parent_cycle')->translate();
                } elseif ((int) $value !== $group->parent_id && ! Group::query()->active()->whereKey($value)->exists()) {
                    // A changed parent must be active; the current one may stay even if it is not.
                    $fail('dmv_settings.error.inactive_group')->translate();
                }
            }],
            'kind' => ['required', Rule::enum(Kind::class)],
            'listing_visibility' => ['required', Rule::enum(ListingVisibility::class)],
            'display_order' => ['required', 'integer', 'min:0', 'max:65535'],
            'logo_key' => ['nullable', Rule::enum(GroupLogo::class)],
            'time_boxed' => ['required', 'boolean'],
            'start_date' => ['exclude_unless:time_boxed,true', 'required', 'date'],
            'end_date' => ['exclude_unless:time_boxed,true', 'required', 'date', 'after_or_equal:start_date'],
            ...array_fill_keys(DmvSettingsController::FLAGS, ['required', 'boolean']),
            'hours_multiplier' => ['required', 'numeric', 'min:0', 'max:999.999', 'decimal:0,3'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'end_date.after_or_equal' => __('dmv_settings.error.end_before_start'),
        ];
    }

    /**
     * Refuse the Group itself, on a `group` error the form shows above Save. Then refuse
     * turning a capability off while a Member of the Group holds a role that needs it
     * (#844, ADR-0036 §5), on that flag's key, naming each such role. Nothing behind the
     * flag is deleted: the officer reassigns the roles, then turns the flag off.
     *
     * @return array<int, Closure(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $group = $this->group();

            if (! DmvSettingsController::editable($group)) {
                $validator->errors()->add('group', __('dmv_settings.error.not_editable'));
            } elseif ($group->lifecycle_state === LifecycleState::Archived) {
                $validator->errors()->add('group', __('dmv_settings.error.archived'));
            } else {
                $this->guardCapabilities($validator, $group);
                $this->guardStewardships($validator, $group);
            }
        }];
    }

    /** Add an error on each capability switched off that a role held on the Group needs. */
    private function guardCapabilities(Validator $validator, Group $group): void
    {
        $turningOff = array_filter(
            DmvSettingsController::FLAGS,
            fn (string $flag) => $group->{$flag} && $this->has($flag) && ! $this->boolean($flag),
        );
        if ($turningOff === []) {
            return;
        }

        $held = GroupMemberRole::query()
            ->whereRelation('groupMember', 'group_id', $group->id)
            ->distinct()
            ->pluck('role');

        foreach ($turningOff as $flag) {
            $labels = $held
                ->filter(fn (Role $role) => $role->requiredCapability() === $flag)
                ->map(fn (Role $role) => __("group.role.{$role->value}"))
                ->sort()
                ->values();

            if ($labels->isNotEmpty()) {
                $validator->errors()->add($flag, trans_choice('dmv_settings.error.capability_in_use', $labels->count(), [
                    'roles' => $labels->join(', ', ' '.__('dmv_settings.and').' '),
                ]));
            }
        }
    }

    /**
     * Refuse a save that would leave a stewardship holder inactive or without a form (ADR-0036
     * §2): kind made Container, or time-boxed with an end before today. Same guard and copy as
     * archiving, on the field that would cause it.
     */
    private function guardStewardships(Validator $validator, Group $group): void
    {
        $errors = $validator->errors();
        $toContainer = ! $errors->has('kind') && $this->input('kind') === Kind::Container->value;
        $ended = ! $errors->has('end_date') && $this->boolean('time_boxed')
            && strtotime((string) $this->input('end_date')) !== false
            && Carbon::parse($this->input('end_date'))->startOfDay()->lt(now()->startOfDay());
        if (! $toContainer && ! $ended) {
            return;
        }

        $held = $group->stewardships()->get();
        if ($held->isEmpty()) {
            return;
        }

        $message = __('dmv_settings.error.archive_steward', [
            'group' => $group->name,
            'stewardship' => $held
                ->map(fn (GroupStewardship $row) => __("dmv_settings.stewardship.{$row->function->value}.title"))
                ->join(', ', ' '.__('dmv_settings.and').' '),
        ]);

        if ($toContainer) {
            $errors->add('kind', $message);
        }
        if ($ended) {
            $errors->add('end_date', $message);
        }
    }

    /**
     * The validated structure, ready to save. A Group no longer time-boxed drops its dates.
     *
     * @return array<string, mixed>
     */
    public function structure(): array
    {
        $structure = $this->validated();

        if (! $structure['time_boxed']) {
            $structure['start_date'] = null;
            $structure['end_date'] = null;
        }

        return $structure;
    }

    private function group(): Group
    {
        return $this->route('group');
    }
}
