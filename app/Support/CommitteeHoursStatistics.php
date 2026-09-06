<?php

namespace App\Support;

use App\Models\Group;
use App\Models\HoursRecord;
use Illuminate\Support\Collection;

/**
 * The DMV-wide committee-statistics matrix (#413, PRD #406, ADR-0022 §8) — the arithmetic
 * behind Summary and Detailed Committee Statistics, the org-wide twin of {@see GroupHoursMatrix}.
 *
 * Given the DMV root Group and a fiscal year it returns, for the root and each of its
 * direct-child committees, a twelve-month April-to-March breakdown of shifts, meetings, and
 * extra hours over that Group's whole subtree — to any depth — plus the year-to-date. A
 * grandchild folds into its committee ancestor, exactly as legacy folds a sub-committee into
 * the committee whose symbol it stored (ADR-0022, legacy finding 3).
 *
 * The **root's own row is the complete org total**: its subtree is the whole department, so
 * hours logged directly against the DMV — and every committee's — are counted, and nothing is
 * orphaned. Detailed reads the committee rows and the root row as its total; Summary reads the
 * root row as its grand total and the separate scheduling list as its scheduled section.
 *
 * That scheduling list is a **flat list of every Group that runs scheduling**, at any depth,
 * carrying its own hours rather than its subtree's. It is deliberately not the committee rows:
 * the top of the DMV tree is page-less Container sections (PRD #289) that run no scheduling, so
 * a capability filter over the committees matched nothing while the programs beneath them held
 * every shift hour in the department.
 *
 * The three parts come straight off the record: `shifts` is `scheduled_hours`, `meetings` is
 * `extra_hours` on rows carrying a Meeting, and `extra` is `extra_hours` on the rest — matching
 * legacy, which stores meeting attendance inside `extra_hours` on meeting rows. `total` is the
 * stored `total_hours`, so the per-Group `hours_multiplier` is honoured exactly once (applied
 * upstream at recalculation and import, ADR-0022 §7), never again here.
 *
 * Bucketing runs in PHP against the `year_month` string, not a database date function, so the
 * result does not depend on the engine's date handling (the sandbox note on engine drift).
 */
class CommitteeHoursStatistics
{
    /**
     * @param  array<int, string>  $months  the twelve `YYYYMM` buckets, April-to-March order
     * @param  list<array<string, mixed>>  $committees  one breakdown per direct-child committee, name-ordered
     * @param  list<array<string, mixed>>  $scheduling  one breakdown per scheduling Group anywhere in the tree
     * @param  array<string, mixed>  $org  the root subtree breakdown — the complete org total
     */
    private function __construct(
        public readonly array $months,
        public readonly array $committees,
        public readonly array $scheduling,
        public readonly array $org,
    ) {}

    /**
     * Build the statistics for the DMV root Group and one fiscal year.
     */
    public static function for(Group $root, int $fiscalYear): self
    {
        $months = OrgTime::fiscalYearMonths($fiscalYear);

        $subtreeIds = $root->descendants()->pluck('id')->push($root->getKey())->all();
        $records = HoursRecord::query()
            ->whereIn('group_id', $subtreeIds)
            ->whereIn('year_month', $months)
            ->get();

        // Visitor interactions are the fourth grain (ADR-0023 §6). They read the summary's
        // composition rule from its one home — Sign-up counts plus a Group's extra interactions —
        // so Detailed and Summary can never diverge. This is a per-Group per-month map; each
        // breakdown rolls it up over its own subtree exactly as it does the three hours grains.
        $interactions = VisitorInteractionStatistics::ownFor($root, $months);

        // Each direct child of the root is a committee; its row rolls up its whole subtree.
        $children = Group::query()
            ->where('parent_id', $root->getKey())
            ->orderBy('name')
            ->get();

        $committees = $children
            ->map(fn (Group $child): array => self::breakdown($child, $records, $months, $interactions))
            ->all();

        // The scheduled section lists every Group that actually runs scheduling, wherever it
        // sits in the tree — not the root's direct children. The top of the DMV tree is
        // page-less Container sections (PRD #289), none of which run scheduling, so filtering
        // the children by the capability matched nothing and the section printed empty while
        // the programs below carried every shift hour in the org.
        //
        // These rows are **own hours, not subtree**: a scheduling Group nested under another
        // would otherwise be counted in both rows, and the column would over-sum. Own-only
        // keeps the section adding up to the org's shift total, which is the number the ROM
        // is given.
        $scheduling = Group::query()
            ->whereIn('id', $subtreeIds)
            ->where('has_scheduling', true)
            ->orderBy('name')
            ->get()
            ->map(fn (Group $group): array => self::breakdown($group, $records, $months, $interactions, includeDescendants: false))
            ->all();

        return new self($months, $committees, $scheduling, self::breakdown($root, $records, $months, $interactions));
    }

    /**
     * The twelve-month breakdown for one Group, plus the year-to-date. Filters the
     * already-loaded record set in memory, so the tree is walked once per Group without a
     * query per month.
     *
     * `$includeDescendants` picks the grain. The committee and org rows roll up the whole
     * subtree, so a grandchild folds into its committee ancestor. The scheduled-section rows
     * pass false and count the Group alone, so two scheduling Groups on the same branch are
     * never both credited with the same hours.
     *
     * `$interactions` is the summary's per-Group per-month composition map ({@see
     * VisitorInteractionStatistics::ownFor()}); the fourth grain rolls it up over the same subtree
     * as the three hours grains, and stays outside `total`, which is hours alone.
     *
     * @param  Collection<int, HoursRecord>  $records  every record in the root subtree this year
     * @param  array<int, string>  $months
     * @param  array<int, array<string, int>>  $interactions  [groupId][YYYYMM] => visitor interactions
     * @return array<string, mixed>
     */
    private static function breakdown(Group $group, Collection $records, array $months, array $interactions, bool $includeDescendants = true): array
    {
        $groupIds = $includeDescendants
            ? $group->descendants()->pluck('id')->push($group->getKey())->all()
            : [$group->getKey()];
        $own = $records->whereIn('group_id', $groupIds);

        $cells = collect(array_map(function (string $yearMonth) use ($own, $groupIds, $interactions): array {
            $forMonth = $own->where('year_month', $yearMonth);
            $shifts = (int) $forMonth->sum('scheduled_hours');
            $meetings = (int) $forMonth->where('meeting_id', '!=', HoursRecord::NO_MEETING)->sum('extra_hours');
            $extra = (int) $forMonth->where('meeting_id', HoursRecord::NO_MEETING)->sum('extra_hours');

            $visitors = 0;
            foreach ($groupIds as $id) {
                $visitors += $interactions[$id][$yearMonth] ?? 0;
            }

            return [
                'year_month' => $yearMonth,
                'shifts' => $shifts,
                'meetings' => $meetings,
                'extra' => $extra,
                'total' => $shifts + $meetings + $extra,
                'interactions' => $visitors,
            ];
        }, $months));

        return [
            'id' => $group->getKey(),
            'name' => $group->name,
            'months' => $cells->all(),
            'ytd' => [
                'shifts' => (int) $cells->sum('shifts'),
                'meetings' => (int) $cells->sum('meetings'),
                'extra' => (int) $cells->sum('extra'),
                'total' => (int) $cells->sum('total'),
                'interactions' => (int) $cells->sum('interactions'),
            ],
        ];
    }
}
