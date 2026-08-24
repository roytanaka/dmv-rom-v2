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
 * orphaned. Summary reads the root row as its grand total and the committees' scheduled hours
 * as its scheduled section; Detailed reads the committee rows and the root row as its total.
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
     * @param  array<string, mixed>  $org  the root subtree breakdown — the complete org total
     */
    private function __construct(
        public readonly array $months,
        public readonly array $committees,
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

        // Each direct child of the root is a committee; its row rolls up its whole subtree.
        $children = Group::query()
            ->where('parent_id', $root->getKey())
            ->orderBy('name')
            ->get();

        $committees = $children
            ->map(fn (Group $child): array => self::breakdown($child, $records, $months))
            ->all();

        return new self($months, $committees, self::breakdown($root, $records, $months));
    }

    /**
     * The twelve-month breakdown for one Group over its whole subtree, plus the year-to-date.
     * Filters the already-loaded record set to the Group's subtree in memory, so the tree is
     * walked once per Group without a query per month.
     *
     * @param  Collection<int, HoursRecord>  $records  every record in the root subtree this year
     * @param  array<int, string>  $months
     * @return array<string, mixed>
     */
    private static function breakdown(Group $group, Collection $records, array $months): array
    {
        $subtreeIds = $group->descendants()->pluck('id')->push($group->getKey())->all();
        $own = $records->whereIn('group_id', $subtreeIds);

        $cells = collect(array_map(function (string $yearMonth) use ($own): array {
            $forMonth = $own->where('year_month', $yearMonth);
            $shifts = (int) $forMonth->sum('scheduled_hours');
            $meetings = (int) $forMonth->where('meeting_id', '!=', HoursRecord::NO_MEETING)->sum('extra_hours');
            $extra = (int) $forMonth->where('meeting_id', HoursRecord::NO_MEETING)->sum('extra_hours');

            return [
                'year_month' => $yearMonth,
                'shifts' => $shifts,
                'meetings' => $meetings,
                'extra' => $extra,
                'total' => $shifts + $meetings + $extra,
            ];
        }, $months));

        return [
            'id' => $group->getKey(),
            'name' => $group->name,
            'has_scheduling' => $group->has_scheduling,
            'months' => $cells->all(),
            'ytd' => [
                'shifts' => (int) $cells->sum('shifts'),
                'meetings' => (int) $cells->sum('meetings'),
                'extra' => (int) $cells->sum('extra'),
                'total' => (int) $cells->sum('total'),
            ],
        ];
    }
}
