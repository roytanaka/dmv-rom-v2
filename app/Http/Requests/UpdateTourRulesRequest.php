<?php

namespace App\Http\Requests;

use App\Models\Tour;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Editing a Group's status rules (#793, ADR-0033 §7): the trainee Tour (optional), the starter
 * Tours (zero or more) and the LOA rule, always submitted together. Authorized through the
 * TourPolicy's `manageRules` gate (the Chair of a vetting Group, plus the super-tier). Every Tour
 * id must name a Tour of the route-bound Group. The trainee Tour cannot also be a starter Tour:
 * becoming Full would then both activate and deactivate it.
 */
class UpdateTourRulesRequest extends FormRequest
{
    /**
     * Authorize against the TourPolicy on the route-bound Group.
     */
    public function authorize(): bool
    {
        return $this->user()->can('manageRules', [Tour::class, $this->route('group')]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $groupTour = Rule::exists('tours', 'id')->where('group_id', $this->route('group')->id);

        return [
            'trainee_tour_id' => ['present', 'nullable', 'integer', $groupTour],
            'starter_tour_ids' => ['present', 'array'],
            'starter_tour_ids.*' => ['integer', 'distinct', $groupTour],
            'loa_removes_qualifications' => ['required', 'boolean'],
        ];
    }

    /**
     * Refuse a trainee Tour that is also a starter Tour.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $trainee = $this->input('trainee_tour_id');

            if ($trainee !== null && in_array((int) $trainee, array_map('intval', (array) $this->input('starter_tour_ids', [])), true)) {
                $validator->errors()->add('starter_tour_ids', trans('group.tour_rules.trainee_is_starter'));
            }
        });
    }
}
