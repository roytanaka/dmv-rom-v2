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
 * The reports half — {@see viewReports()} for a Group's own reports and {@see viewOrgReports()}
 * for the DMV-wide ones — is the closed counterpart, gated to officers. The super-tier
 * short-circuit lives in the single `Gate::before` (AppServiceProvider) and is never re-checked
 * here.
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
     * Who may read the DMV-wide reports (ADR-0022 §4, §8): a Chair, Secretary, or Statistician
     * of the **DMV root Group**, a Member holding the **Records** stewardship (member-admin
     * authority), or the super-tier (via the single `Gate::before`). These are the six org-wide
     * fiscal-year reports — the single output the whole Hours feature exists to produce.
     *
     * This is a wider gate than {@see viewReports()}: the DMV Secretary reaches it (no Group
     * report gate admits a Secretary), and so does the Records stewardship, because member
     * administration answers standing questions across the whole roster. An ordinary Member —
     * and a Chair of any other Group, however senior in their own — reaches none of it, because
     * the org's per-Member numbers are not open reading.
     *
     * Takes no Group: the reports are always rooted at the DMV root, resolved here by its
     * single reserved slug so callers ask the plain question "may this actor read the org
     * reports". Returns false when no root Group exists (an unseeded install), never erroring.
     */
    public function viewOrgReports(Member $actor): bool
    {
        if ($actor->hasMemberAdminAuthority()) {
            return true;
        }

        $root = Group::where('slug', Group::ROOT_SLUG)->first();

        return $root !== null && (
            $actor->canActAs(Role::Chair, $root)
            || $actor->canActAs(Role::Secretary, $root)
            || $actor->canActAs(Role::Statistician, $root)
        );
    }

    /**
     * Who may read Summary Visitor Interactions (ADR-0023 §6): **any signed-in Member**. This is
     * the one DMV-wide report that is not officer-gated, and that is deliberate — it is an
     * aggregate with no personal data in it, and it is legacy's own choice, the sole item in the
     * branch offered to members who fail the officer test that gates {@see viewOrgReports()}.
     *
     * Written as a real method beside the gate it is the exception to, rather than left as an
     * ungated route, so the exception to #406 story 59 (DMV-wide reports are officer-only) is
     * named in code and cannot be mistaken for a hole in that rule. The route's `auth` middleware
     * supplies the "signed-in" half; this returns true for every authenticated actor.
     */
    public function viewVisitorSummary(Member $actor): bool
    {
        return true;
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
