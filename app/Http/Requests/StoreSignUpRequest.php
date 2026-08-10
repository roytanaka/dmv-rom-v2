<?php

namespace App\Http\Requests;

use App\Models\SignUp;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Taking a Shift (#357, PRD #352, ADR-0021 §Sign-up) — the self-service Sign-up. The actor
 * and the Shift are the whole request: the Member is the authenticated user, the Shift comes
 * from the route, and there are no body fields. Authorization is structural here —
 * `authorize()` delegates to the SignUpPolicy, which resolves the two floors and the Shift's
 * `audience`; the controller then seats the current user.
 *
 * `authorize()` answers *"may this person work at all, here"* (the policy); the state checks
 * below answer *"is a seat available for them right now"*. **Capacity binds everyone**, and
 * **one Member holds at most one seat on a Shift** — the storage-layer unique constraint is
 * the real guard, and these checks turn the ordinary full-Shift and double-tap into clean
 * validation errors rather than a rejected insert. Two Sign-ups on *overlapping* Shifts stay
 * allowed, deliberately unchecked.
 */
class StoreSignUpRequest extends FormRequest
{
    /**
     * Authorize against the SignUpPolicy: the actor must be able to take the route-bound
     * Shift — clearing the DMV-wide floor, the per-Group floor, and the Shift's `audience`.
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', [SignUp::class, $this->route('shift')]);
    }

    /**
     * No body fields accompany a self-service Sign-up: the seat is the current user on the
     * route-bound Shift.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }

    /**
     * The state guards, run against the route-bound Shift: a Member already holding a seat
     * on it is refused (the one-seat rule, backed by the unique constraint), and a Shift
     * whose Sign-up count has reached its capacity is full and refused. Resolved in PHP so
     * neither comparison depends on the DB engine.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $shift = $this->route('shift');

            if ($shift->signUps()->where('member_id', $this->user()->getKey())->exists()) {
                $validator->errors()->add('shift', trans('group.scheduling_panel.already_signed_up'));

                return;
            }

            if ($shift->signUps()->count() >= $shift->capacity) {
                $validator->errors()->add('shift', trans('group.scheduling_panel.shift_full'));
            }
        });
    }
}
