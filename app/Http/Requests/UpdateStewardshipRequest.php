<?php

namespace App\Http\Requests;

use App\Enums\StewardshipFunction;
use App\Models\Group;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Reassign one stewardship (#846, ADR-0036 §7). Super-tier only, through the Group-less
 * `administer-groups` gate, so an archived holder never blocks the move away from it.
 *
 * Member administration and Applicants take exactly one Group (`group_id`): `Group::stewardOf()`
 * reads one holder, and an empty value would leave nobody holding the authority. Org-wide sender
 * takes zero or more (`group_ids`). Every holder must be an active, non-root Group.
 */
class UpdateStewardshipRequest extends FormRequest
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
        if ($this->stewardship()->hasSingleHolder()) {
            return [
                'group_id' => ['bail', 'required', 'integer', $this->activeHolders()],
            ];
        }

        return [
            'group_ids' => ['bail', 'array', $this->activeHolders()],
            'group_ids.*' => ['integer'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $required = __("dmv_settings.error.{$this->stewardship()->value}_required");

        return [
            'group_id.required' => $required,
            'group_id.integer' => $required,
        ];
    }

    /** The stewardship named in the route; the route admits only the live ones. */
    public function stewardship(): StewardshipFunction
    {
        return StewardshipFunction::from($this->route('function'));
    }

    /**
     * The validated holder ids, one for a single-holder stewardship, any number for org-wide sender.
     *
     * @return list<int>
     */
    public function holderIds(): array
    {
        $validated = $this->validated();

        return $this->stewardship()->hasSingleHolder()
            ? [(int) $validated['group_id']]
            : array_values(array_unique(array_map('intval', $validated['group_ids'] ?? [])));
    }

    /** Fails unless every id names an active Group other than the root (it holds nothing). */
    private function activeHolders(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $ids = collect((array) $value)->map(fn ($id) => (int) $id)->unique();

            $active = Group::query()->active()->whereKey($ids->all())->get()
                ->reject(fn (Group $group) => $group->isRoot());

            if ($active->count() !== $ids->count()) {
                $fail('dmv_settings.error.inactive_group')->translate();
            }
        };
    }
}
