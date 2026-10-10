<?php

namespace App\Http\Requests;

use App\Enums\MembershipStatus;
use App\Models\Tour;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Adding a qualification (#789, ADR-0033 §3, §4) from the by-Tour or by-Member screen.
 * Authorized through the TourPolicy's `manage` gate on the route-bound Group. The Membership must
 * be a current one of this Group (a standing that counts as belonging) and the Tour one of this
 * Group's. The Last vet date is a plain date; nothing expires.
 */
class StoreQualificationRequest extends FormRequest
{
    /**
     * Authorize against the TourPolicy on the route-bound Group.
     */
    public function authorize(): bool
    {
        return $this->user()->can('manage', [Tour::class, $this->route('group')]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $groupId = $this->route('group')->getKey();
        $current = collect(MembershipStatus::cases())
            ->filter(fn (MembershipStatus $status): bool => $status->countsAsBelonging())
            ->map(fn (MembershipStatus $status): string => $status->value)
            ->values()
            ->all();

        return [
            'group_member_id' => [
                'required',
                'integer',
                Rule::exists('group_member', 'id')->where('group_id', $groupId)->whereIn('status', $current),
            ],
            'tour_id' => [
                'required',
                'integer',
                Rule::exists('tours', 'id')->where('group_id', $groupId),
            ],
            'last_vet_date' => ['required', 'date_format:Y-m-d'],
        ];
    }
}
