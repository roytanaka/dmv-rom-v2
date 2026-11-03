<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * A member admin removes a board position from the Member record (#918, ADR-0038 §3).
 */
class DeleteBoardPositionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('administer', $this->route('member'));
    }

    /**
     * No body fields accompany a delete.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
