<?php

namespace App\Support;

use App\Models\Group;
use App\Models\HoursRecord;
use App\Models\SignUp;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Summary Visitor Interactions (#451, PRD #443, ADR-0023 §6) — the department's headline visitor
 * number, composed in exactly one place. Given the DMV root Group and a fiscal year it returns,
 * for every Group in the tree that carries visitor data of its own, a twelve-month April-to-March
 * figure over that Group's whole subtree, plus the year-to-date.
 *
 * **The composition rule, for every Group, with no special cases** (ADR-0023 §6):
 *
 * > a Group's figure for a month is the sum of that Group's **Sign-ups** (`visitor_count` +
 * > `extra_interaction_count`), plus its **extra interactions** ({@see HoursRecord::$extra_interactions}),
 * > rolled up through the whole sub-Group subtree.
 *
 * Two sources, one rule. Legacy assembled the same figure five different ways, double-counted in
 * two places, added a booking audience on top for two Groups, never read the per-shift second
 * column at all, and cut its month at a different point than the hours grid beside it. All of that
 * goes: {@see own()} is the one place the rule lives, and {@see figure()} folds it over the subtree.
 *
 * **Which Groups get a row.** Every Group in the tree whose *own* figure is non-zero across the
 * year — a page-less Container section (PRD #289) carries none, so it never appears, and the
 * programs beneath it do. Each listed Group's displayed figure rolls up its whole subtree, so a
 * program's sub-cohorts fold into it; the DMV root, if it carries its own extra interactions,
 * reads as the complete department total. Name-ordered for a stable display.
 *
 * **The month window is the hours reports' window** — the same twelve {@see OrgTime} buckets, so
 * the visitor figure and the hours grid on the same screen cover the same period. A Sign-up falls
 * in the month of its Shift's `ends_at` (ADR-0023 §4), resolved in the org zone; an Hours record
 * falls in its `year_month`. Both are bucketed in PHP, so the result does not depend on the
 * engine's date handling (the sandbox note on engine drift).
 */
class VisitorInteractionStatistics
{
    /**
     * @param  array<int, string>  $months  the twelve `YYYYMM` buckets, April-to-March order
     * @param  list<array<string, mixed>>  $groups  one row per Group carrying visitor data, name-ordered
     */
    private function __construct(
        public readonly array $months,
        public readonly array $groups,
    ) {}

    /**
     * Build the statistics for the DMV root Group and one fiscal year.
     */
    public static function for(Group $root, int $fiscalYear): self
    {
        $months = OrgTime::fiscalYearMonths($fiscalYear);
        $zone = config('app.org_timezone');

        $subtree = $root->descendants()->prepend($root);
        $subtreeIds = $subtree->pluck('id')->all();

        // The two sources of the composition rule, loaded once. Sign-ups are reached through the
        // Shift's Schedule, which carries the owning Group; Hours records carry the Group directly.
        $signUps = SignUp::query()
            ->whereHas('shift.schedule', fn (Builder $query) => $query->whereIn('group_id', $subtreeIds))
            ->with('shift.schedule')
            ->get();

        $records = HoursRecord::query()
            ->whereIn('group_id', $subtreeIds)
            ->whereIn('year_month', $months)
            ->get();

        // The one place the rule lives: each Group's *own* interactions per bucket, [id][ym] => int.
        $own = self::own($subtreeIds, $months, $signUps, $records, $zone);

        // A Group appears iff it carries visitor data of its own this year; its figure rolls up
        // the whole subtree. Name-ordered so the report is stable across runs.
        $groups = $subtree
            ->sortBy('name')
            ->filter(fn (Group $group): bool => array_sum($own[$group->getKey()]) > 0)
            ->map(fn (Group $group): array => self::figure($group, $own, $months))
            ->values()
            ->all();

        return new self($months, $groups);
    }

    /**
     * The composition rule — every Group's own interactions per month, before any subtree rollup.
     * This is the sole place `visitor_count`, `extra_interaction_count` and `extra_interactions`
     * are added together. Every subtree Group is seeded with twelve zero buckets, so a Group with
     * no data still reads zero rather than dropping out of the map.
     *
     * @param  list<int>  $subtreeIds
     * @param  array<int, string>  $months
     * @param  Collection<int, SignUp>  $signUps
     * @param  Collection<int, HoursRecord>  $records
     * @return array<int, array<string, int>> [groupId][YYYYMM] => interactions
     */
    private static function own(array $subtreeIds, array $months, Collection $signUps, Collection $records, string $zone): array
    {
        $own = [];
        foreach ($subtreeIds as $id) {
            $own[$id] = array_fill_keys($months, 0);
        }

        foreach ($signUps as $signUp) {
            $groupId = $signUp->shift->schedule->group_id;
            $yearMonth = $signUp->shift->ends_at->setTimezone($zone)->format('Ym');
            if (! isset($own[$groupId][$yearMonth])) {
                continue; // outside the twelve buckets, or a Group not in the tree
            }
            $own[$groupId][$yearMonth] += (int) $signUp->visitor_count + (int) $signUp->extra_interaction_count;
        }

        foreach ($records as $record) {
            if (! isset($own[$record->group_id][$record->year_month])) {
                continue;
            }
            $own[$record->group_id][$record->year_month] += (int) $record->extra_interactions;
        }

        return $own;
    }

    /**
     * One Group's report row — its twelve-month figure rolled up over its whole subtree (self plus
     * every descendant, to any depth), the year-to-date, and the incomplete marker. A month with
     * nothing on file reads zero, so the twelve columns always line up.
     *
     * @param  array<int, array<string, int>>  $own
     * @param  array<int, string>  $months
     * @return array<string, mixed>
     */
    private static function figure(Group $group, array $own, array $months): array
    {
        $subtreeIds = $group->descendants()->pluck('id')->push($group->getKey())->all();

        $cells = array_map(function (string $yearMonth) use ($subtreeIds, $own): array {
            $interactions = 0;
            foreach ($subtreeIds as $id) {
                $interactions += $own[$id][$yearMonth] ?? 0;
            }

            return ['year_month' => $yearMonth, 'interactions' => $interactions];
        }, $months);

        return [
            'id' => $group->getKey(),
            'name' => $group->name,
            'incomplete' => $group->visitor_figures_await_booking,
            'months' => $cells,
            'ytd' => array_sum(array_column($cells, 'interactions')),
        ];
    }
}
