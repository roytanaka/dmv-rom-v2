<?php

namespace App\Http\Requests;

use App\Enums\Category;
use App\Enums\DepartureReason;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A member admin's new Category history entry (#920, ADR-0038 §4): a Category and the date it
 * took effect, and a departure reason when the Category is Resigned or Withdrawn.
 */
class StoreCategoryChangeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('administer', $this->route('member'));
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'category' => ['required', Rule::enum(Category::class)],
            'date' => ['required', 'date'],
            'departure_reason' => [
                Rule::requiredIf(in_array($this->input('category'), [Category::Resigned->value, Category::Withdrawn->value], true)),
                'nullable',
                Rule::enum(DepartureReason::class),
            ],
        ];
    }
}
