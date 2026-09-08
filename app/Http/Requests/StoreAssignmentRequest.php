<?php

namespace App\Http\Requests;

use App\Models\Member;
use App\Models\SignUp;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Officer assignment (#359, PRD #352, ADR-0021 §Sign-up) — a Scheduler placing a named Member
 * on a Shift, parallel to the self-service {@see StoreSignUpRequest}. Unlike that seam this
 * one carries a body: the `member_id` of the person being placed. Authorization is structural
 * — `authorize()` delegates to `SignUpPolicy::assign`, which resolves the schedule-admin gate
 * on the actor *and* both sign-up floors on the placed Member (but never the `audience`).
 *
 * `authorize()` answers *"may this Scheduler place this person here at all"* (the policy); the
 * state checks below answer *"is a seat available for them right now"*. **Capacity binds the
 * Scheduler too, with no override** (ADR-0021 §Sign-up), and **one Member holds at most one
 * seat on a Shift** — the storage-layer unique constraint is the real guard, and these checks
 * turn the full-Shift and the already-placed cases into clean validation errors rather than a
 * rejected insert.
 */
class StoreAssignmentRequest extends FormRequest
{
    /**
     * Authorize against the SignUpPolicy: the actor must be able to place the requested
     * Member on the route-bound Shift — clearing the schedule-admin gate and the placed
     * Member's two floors. A `member_id` that names nobody fails the `exists` rule below as
     * a validation error rather than reaching the policy.
     */
    public function authorize(): bool
    {
        $member = Member::find($this->input('member_id'));

        if ($member === null) {
            return true;
        }

        return $this->user()->can('assign', [SignUp::class, $this->route('shift'), $member]);
    }

    /**
     * The placed Member's id is the whole body. It must name a real Member; whether that
     * Member may be seated here is the policy's and the state checks' business.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'member_id' => ['required', 'integer', 'exists:members,id'],
        ];
    }

    /**
     * The state guards, run against the route-bound Shift: a Member already holding a seat on
     * it is refused (the one-seat rule, backed by the unique constraint), and a Shift whose
     * Sign-up count has reached its capacity is full and refused — the Scheduler included,
     * with no override. Resolved in PHP so neither comparison depends on the DB engine.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $shift = $this->route('shift');

            if ($shift->signUps()->where('member_id', $this->input('member_id'))->exists()) {
                $validator->errors()->add('member_id', trans('group.scheduling_panel.already_signed_up'));

                return;
            }

            if ($shift->signUps()->count() >= $shift->capacity) {
                $validator->errors()->add('member_id', trans('group.scheduling_panel.shift_full'));
            }
        });
    }
}
