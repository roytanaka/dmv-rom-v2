<?php

namespace App\Http\Requests;

use App\Models\Shift;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A Member deleting their own self-authored Shift (#585, ADR-0026 §1) — which drops their
 * Sign-up with it, until the Shift starts. Authorization is the only gate: `authorize()`
 * delegates to {@see Shift::isSelfServeOwnedBy()}, the derived-ownership rule (self-serve
 * Group, capacity 1, the actor's own single seat, not yet started), which binds super-tier
 * too (#647). No body fields — the Shift is the route binding.
 */
class DeleteSelfServeShiftRequest extends FormRequest
{
    /**
     * Authorize by ownership: the actor owns the route-bound Shift by the derived rule and it
     * has not started.
     */
    public function authorize(): bool
    {
        return $this->route('shift')->isSelfServeOwnedBy($this->user());
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
