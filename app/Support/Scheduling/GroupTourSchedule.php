<?php

namespace App\Support\Scheduling;

use App\Enums\ScheduleState;
use App\Models\Group;
use App\Models\Schedule;
use Carbon\CarbonImmutable;
use DateTimeInterface;

/**
 * The month's group-tour Schedule (#795, ADR-0032 §4) — the one place that finds or creates the
 * Schedule a Booking's Shift sits on. Adding a Booking (#795) and moving one to another month
 * (#796) both resolve it here.
 *
 * One per Group and month, marked by `group_tour_month` (the month's first day), never by name:
 * the name is officer-editable. Created published and spanning the whole month, so a Booking made
 * months ahead is visible at once and no draft daily Schedule ever hides it. Named from the
 * Group's group-tour label plus the month and year ("Group tours – October 2026"); the label is
 * content (ADR-0004), the month is spelled in the given locale.
 */
class GroupTourSchedule
{
    /**
     * The group-tour Schedule for the month the day falls in, created and published if the month
     * has none yet. `$day` is read on the org wall clock. `$locale` spells the month in a new
     * Schedule's name and defaults to the app locale (the Booker's language on a write); an
     * existing Schedule keeps its name.
     */
    public static function for(Group $group, DateTimeInterface $day, ?string $locale = null): Schedule
    {
        $month = self::monthOf($day);

        $schedule = Schedule::firstOrCreate(
            ['group_id' => $group->id, 'group_tour_month' => $month->toDateString()],
            [
                'name' => self::nameFor($group, $month, $locale ?? app()->getLocale()),
                'starts_on' => $month->toDateString(),
                'ends_on' => $month->endOfMonth()->toDateString(),
                'state' => ScheduleState::Published,
            ],
        );

        $schedule->setRelation('group', $group);

        return $schedule;
    }

    /**
     * The name a new group-tour Schedule takes: the Group's label, then the month and year in the
     * locale ("Group tours – October 2026", "Visites de groupe – octobre 2026"). Falls back to the
     * translated "Group tours" when the Group has set no label.
     */
    public static function nameFor(Group $group, DateTimeInterface $month, string $locale): string
    {
        $label = $group->group_tour_label ?: trans('group.bookings.default_label', [], $locale);

        return $label.' – '.CarbonImmutable::instance($month)->locale($locale)->translatedFormat('F Y');
    }

    /**
     * The first day of the month the instant falls in, on the org wall clock.
     */
    public static function monthOf(DateTimeInterface $day): CarbonImmutable
    {
        return CarbonImmutable::instance($day)
            ->setTimezone(config('app.org_timezone'))
            ->startOfMonth()
            ->startOfDay();
    }
}
