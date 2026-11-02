<?php

namespace App\Http\Requests;

use App\Models\Member;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Adding one Member as a Guest (ADR-0034 §3): the Invitation's Host or the super-tier.
 * Any current Member will do, and current means the standing that gives a Directory
 * listing, so the Host need not share a Group with them.
 */
class StoreGuestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manageGuests', $this->route('invitation'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'member' => ['required', 'integer', function (string $attribute, mixed $value, Closure $fail): void {
                if (! Member::query()->inDirectory()->whereKey($value)->exists()) {
                    $fail(__('validation.exists', ['attribute' => $attribute]));
                }
            }],
        ];
    }
}
