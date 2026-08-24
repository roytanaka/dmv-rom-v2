<?php

namespace App\Policies;

use App\Enums\AccessTier;
use App\Enums\ListingVisibility;
use App\Models\Group;
use App\Models\Member;

/**
 * Authorization for Hours records (ADR-0022 §4) — the entry write seam of this pass.
 *
 * Extra-hours entry is the **open half** of the feature: any logged-in Member whose
 * DMV-wide Category still permits participation may record extra hours on any Group whose
 * page they can open, including a Group they do not belong to, because anyone is free to
 * help anyone else. The two clauses:
 *
 * - **Category participates.** A Member whose Category has withdrawn from the DMV
 *   (Withdrawn / Resigned / Deceased — {@see AccessTier::None}) gets no entry form and
 *   cannot write; a departed person cannot accrue hours. LOA keeps its Full standing and
 *   may still enter, deliberately unlike the sign-up floor.
 * - **Can open the Group.** Entry follows the page's own openability — a Private Group
 *   discloses nothing to a non-member, so its Hours surface (and its write seam) is closed
 *   to one, mirroring the Group page's Private gate.
 *
 * The reports half (viewReports / viewOrgReports) is deliberately *not* here yet — it lands
 * with the reporting slices. The super-tier short-circuit lives in the single `Gate::before`
 * (AppServiceProvider) and is never re-checked here.
 *
 * The writing Member always comes from the authenticated session (ADR-0022 §4, the named
 * security deviation from legacy): this policy answers "may *this actor* write on this
 * Group", never "whose hours" — no route, request body, or field names the target.
 */
class HoursRecordPolicy
{
    /**
     * Who may enter extra hours on a Group: any Member whose Category still participates,
     * on any Group whose page they can open.
     */
    public function create(Member $actor, Group $group): bool
    {
        return $actor->category->accessTier() !== AccessTier::None
            && $this->canOpen($actor, $group);
    }

    /**
     * Whether the actor can open the Group's page at all (ADR-0019): Public and Group
     * visibility are org-open, so any logged-in Member reaches the Hours surface; a Private
     * Group is closed to a non-member, exactly as its page is. Consistent with the Group
     * page gate and the SchedulePolicy's listing check.
     */
    private function canOpen(Member $actor, Group $group): bool
    {
        return $group->listing_visibility !== ListingVisibility::Private
            || $actor->membershipIn($group) !== null;
    }
}
