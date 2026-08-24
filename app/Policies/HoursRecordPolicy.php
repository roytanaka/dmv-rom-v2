<?php

namespace App\Policies;

use App\Enums\AccessTier;
use App\Enums\ListingVisibility;
use App\Enums\Role;
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
     * Who may read a Group's fiscal-year reports (ADR-0022 §4, §5): a Chair or Statistician
     * **of that Group**, a Chair or Statistician of **any ancestor** Group however far up the
     * tree, or the super-tier (via the single `Gate::before`). The ancestor clause is legacy's
     * parent-chair test, kept and generalized past two levels — it is the one place a read
     * crosses the parentage boundary ADR-0019 draws for content, because hours are a statistic
     * that aggregates, not content (§5).
     *
     * Reports are deliberately **not** open reading: an ordinary Member of the Group gets
     * nothing, not even a Group total, and a Member of a sibling Group gets nothing either —
     * only the parent relationship widens a report. This is stricter than the open create half.
     */
    public function viewReports(Member $actor, Group $group): bool
    {
        // Walk to the root by explicit query — reading the `parent` relation lazily would trip
        // strict mode's lazy-load guard, and the tree is only tens of Groups deep.
        $node = $group;
        while ($node !== null) {
            if ($actor->canActAs(Role::Chair, $node) || $actor->canActAs(Role::Statistician, $node)) {
                return true;
            }
            $node = $node->parent_id !== null ? Group::find($node->parent_id) : null;
        }

        return false;
    }

    /**
     * Who may recalculate a Group's scheduled hours from its Sign-ups (ADR-0022 §2): a
     * Scheduler or Chair (Chair-implication folded in by {@see Member::canActAs()}) of a Group
     * that runs scheduling. This hangs off the **scheduling** capability, not the hours one —
     * the derived half of the record is a scheduling action, so it does not appear on a Group
     * that runs no scheduling (the control would have nothing to do), and an ordinary Member
     * cannot rewrite the derived half of a peer's record. The same gate the SchedulePolicy
     * uses for every authoring ability.
     */
    public function recalculate(Member $actor, Group $group): bool
    {
        return $group->has_scheduling
            && $actor->canActAs(Role::Scheduler, $group);
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
