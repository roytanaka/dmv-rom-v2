<?php

namespace App\Support;

use App\Models\Group;
use App\Models\HoursRecord;

/**
 * The fiscal-year hours matrix (#411, PRD #406, ADR-0022 §5) — the feature's one new seam.
 *
 * Given a Group and a fiscal year it answers three questions the Group and DMV-wide reports
 * all rest on: the Group's **own** twelve monthly buckets, the same twelve **including every
 * descendant** to any depth, and the year-to-date total for each. The org-wide reports reuse
 * it rooted at the DMV Group.
 *
 * Its arithmetic — twelve April-to-March buckets from {@see OrgTime}, own-versus-subtree over
 * the descendant tree, and the whole-hours totals — is exactly the non-trivial service logic
 * the conventions reserve a unit test for, so it is a plain query object tested directly.
 *
 * It sums the **stored** `total_hours`, so the per-Group `hours_multiplier` is honoured exactly
 * once: it is applied upstream at recalculation and import (ADR-0022 §7), never again here.
 *
 * Bucketing runs in PHP against the `year_month` string, not a database date function, so the
 * result does not depend on the engine's date handling (the sandbox note on engine drift).
 */
class GroupHoursMatrix
{
    /**
     * @param  array<int, string>  $months  the twelve `YYYYMM` buckets, April-to-March order
     * @param  list<int>  $own  the Group's own hours per bucket, aligned to $months
     * @param  list<int>  $subtree  the Group's hours including every descendant, per bucket
     */
    private function __construct(
        public readonly array $months,
        public readonly array $own,
        public readonly array $subtree,
        public readonly int $ownYtd,
        public readonly int $subtreeYtd,
    ) {}

    /**
     * Build the matrix for one Group and fiscal year.
     */
    public static function for(Group $group, int $fiscalYear): self
    {
        $months = OrgTime::fiscalYearMonths($fiscalYear);
        $ownId = $group->getKey();
        $subtreeIds = $group->descendants()->pluck('id')->push($ownId)->all();

        $records = HoursRecord::query()
            ->whereIn('group_id', $subtreeIds)
            ->whereIn('year_month', $months)
            ->get();

        $own = [];
        $subtree = [];
        foreach ($months as $yearMonth) {
            $forMonth = $records->where('year_month', $yearMonth);
            $own[] = (int) $forMonth->where('group_id', $ownId)->sum('total_hours');
            $subtree[] = (int) $forMonth->sum('total_hours');
        }

        return new self($months, $own, $subtree, array_sum($own), array_sum($subtree));
    }
}
