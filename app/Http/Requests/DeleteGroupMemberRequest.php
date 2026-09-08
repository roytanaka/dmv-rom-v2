<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Hard-removing a membership (#192, PRD #186) — the true row delete reserved for the
 * added-in-error case, distinct from a resign (a soft status change that keeps the
 * row and its history). Authorization delegates to the GroupMemberPolicy (an officer
 * of the owning Group, or the super-tier).
 *
 * The "no dependent records" precondition is a data-integrity invariant, not an
 * authority question, so it lives here as a validation rule rather than in the
 * policy — it applies even to the super-tier, who would otherwise pass the
 * `Gate::before` short-circuit and orphan a member's role history. A membership that
 * carries roles must be resigned, not hard-removed.
 */
class DeleteGroupMemberRequest extends FormRequest
{
    /**
     * Authorize against the GroupMemberPolicy: the actor must be able to delete the
     * route-bound membership.
     */
    public function authorize(): bool
    {
        return $this->user()->can('delete', $this->route('membership'));
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

    /**
     * Block the hard-remove when the membership has dependent records (its roles —
     * the only thing FK'd to it today; hours and past participation join later).
     * Fails closed with a clean validation error so the row and its history survive.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->route('membership')->roles()->exists()) {
                $validator->errors()->add('membership', trans('group.roster.cannot_hard_remove'));
            }
        });
    }
}
