<?php

namespace App\Support\Tours;

use App\Enums\MembershipStatus;
use App\Models\GroupMember;
use App\Models\Qualification;
use App\Support\OrgTime;

/**
 * The status rules (#793, ADR-0033 §7): when a Membership's standing changes, its
 * qualifications follow, as in the old app. The Group's settings name the Tours, never a
 * hard-coded id:
 *
 * - Trainee: every qualification goes inactive, then the trainee Tour becomes active.
 * - Full: the starter Tours become active, and the trainee Tour goes inactive.
 * - Emeritus, Resigned, Deceased: every qualification goes inactive.
 * - LOA: every qualification goes inactive, but only when the Group's LOA rule is on.
 * - Any other standing changes nothing.
 *
 * Rules only activate or deactivate rows, never delete them, so a returning Member keeps their
 * history. An activation sets today's Last vet date; a row already active is left as it is.
 * The caller compares old and new standing ({@see GroupMemberController}); a Group without
 * vetting has no rules.
 */
class QualificationRules
{
    /**
     * Apply the rules for a Membership whose standing just became its current one. `$from` is
     * the standing before the change, or null for a new Membership.
     */
    public function apply(GroupMember $membership, ?MembershipStatus $from): void
    {
        $to = $membership->status;
        $group = $membership->loadMissing('group')->group;

        if ($from === $to || ! $group->has_vetting) {
            return;
        }

        match ($to) {
            MembershipStatus::Trainee => $this->becomeTrainee($membership, $group->trainee_tour_id),
            MembershipStatus::Full => $this->becomeFull($membership, $group->trainee_tour_id),
            MembershipStatus::Emeritus, MembershipStatus::Resigned, MembershipStatus::Deceased => $this->deactivateAll($membership),
            MembershipStatus::Loa => $group->loa_removes_qualifications ? $this->deactivateAll($membership) : null,
            default => null,
        };
    }

    private function becomeTrainee(GroupMember $membership, ?int $traineeTourId): void
    {
        $membership->qualifications()
            ->when($traineeTourId !== null, fn ($query) => $query->where('tour_id', '!=', $traineeTourId))
            ->update(['active' => false]);

        if ($traineeTourId !== null) {
            $this->activate($membership, [$traineeTourId]);
        }
    }

    private function becomeFull(GroupMember $membership, ?int $traineeTourId): void
    {
        $starterIds = $membership->group->tours()->where('starter', true)->pluck('id')->all();

        $this->activate($membership, $starterIds);

        if ($traineeTourId !== null && ! in_array($traineeTourId, $starterIds, true)) {
            $membership->qualifications()->where('tour_id', $traineeTourId)->update(['active' => false]);
        }
    }

    private function deactivateAll(GroupMember $membership): void
    {
        $membership->qualifications()->update(['active' => false]);
    }

    /**
     * Make the Membership's qualification on each Tour active with today's Last vet date —
     * reactivating an inactive row or adding a missing one. An active row is left untouched.
     *
     * @param  list<int>  $tourIds
     */
    private function activate(GroupMember $membership, array $tourIds): void
    {
        $today = OrgTime::today()->toDateString();

        foreach ($tourIds as $tourId) {
            $qualification = Qualification::firstOrNew([
                'group_member_id' => $membership->id,
                'tour_id' => $tourId,
            ]);

            if ($qualification->exists && $qualification->active) {
                continue;
            }

            $qualification->fill(['active' => true, 'last_vet_date' => $today])->save();
        }
    }
}
