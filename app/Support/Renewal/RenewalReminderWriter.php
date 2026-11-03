<?php

namespace App\Support\Renewal;

use App\Enums\Category;
use App\Enums\DeliveryKind;
use App\Enums\DeliveryState;
use App\Models\Delivery;
use App\Models\Member;
use App\Models\RenewalSetting;
use App\Support\Notices\RenewalReminderSummaryNoticeWriter;
use App\Support\OrgTime;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * The Renewal reminders (#942, ADR-0038 §9, §14.9), a step of the daily pass. It **only writes
 * rows**; the Drain sends them.
 *
 * For each of Active and Sustaining, once today (org time) is on or after the Category's send date
 * plus six weeks, the first series is open; at ten weeks, the second replaces it. Every Member of the
 * Category whose paid-through year is below the renewal year (or unset), without the no-email flag,
 * gets one {@see DeliveryKind::RenewalReminder} Delivery in the open series, written with
 * {@see Delivery::firstOrCreate} on the (kind, series, series year, Member) grain. That is the sent
 * record that makes the pass a window rather than the Legacy app's exact-day match: a second run
 * writes nothing, and a missed day, or a Member missed earlier, catches up.
 *
 * The body is the settings text, snapshotted into the payload so the sent record shows what went.
 * Nothing is written while the show flag is off or no renewal year is set. The Beta mail-off switch
 * is read by the pass before this step runs.
 */
class RenewalReminderWriter
{
    /** The weeks after the send date each series opens, keyed by series number. */
    private const SERIES_WEEKS = [1 => 6, 2 => 10];

    public function __construct(private RenewalReminderSummaryNoticeWriter $summary) {}

    public function write(): void
    {
        $settings = RenewalSetting::current();

        if (! $settings->show_renewal || $settings->renewal_year === null) {
            return;
        }

        $today = OrgTime::today();

        /** @var array<string, list<string>> $mailed */
        $mailed = [];

        foreach ($this->series($settings) as $family => [$category, $sendDate, $message]) {
            $number = $sendDate === null ? null : $this->openSeries(CarbonImmutable::parse($sendDate), $today);

            if ($number === null) {
                continue;
            }

            $series = $family.'-'.$number;
            $names = $this->writeSeries($series, $category, $settings->renewal_year, $message($number));

            if ($names !== []) {
                $mailed[$series] = $names;
            }
        }

        if ($mailed !== []) {
            $this->summary->write($mailed, $settings->renewal_year);
        }
    }

    /**
     * The one open series number for a send date, or null before six weeks. The latest series
     * reached wins, so the first closes when the second opens: a Member first caught after ten
     * weeks gets the second reminder only, never both on one day.
     */
    private function openSeries(CarbonImmutable $sendDate, CarbonImmutable $today): ?int
    {
        $open = null;

        foreach (self::SERIES_WEEKS as $number => $weeks) {
            if ($today->gte($sendDate->addWeeks($weeks)->startOfDay())) {
                $open = $number;
            }
        }

        return $open;
    }

    /**
     * The two series families: their Category, send date and message text by series number.
     *
     * @return array<string, array{Category, Carbon|null, \Closure(int): ?string}>
     */
    private function series(RenewalSetting $settings): array
    {
        return [
            'active' => [Category::Active, $settings->active_send_date, fn (int $n): ?string => $settings->{'active_message_'.$n}],
            'sustaining' => [Category::Sustaining, $settings->sustaining_send_date, fn (int $n): ?string => $settings->{'sustaining_message_'.$n}],
        ];
    }

    /**
     * Write one series' missing rows. Returns the full names of the Members this run wrote for.
     *
     * @return list<string>
     */
    private function writeSeries(string $series, Category $category, int $renewalYear, ?string $message): array
    {
        $members = Member::query()
            ->where('category', $category)
            ->where('no_email', false)
            ->where(fn (Builder $query) => $query
                ->whereNull('paid_through_year')
                ->orWhere('paid_through_year', '<', $renewalYear))
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        $names = [];

        foreach ($members as $member) {
            $row = Delivery::firstOrCreate(
                [
                    'kind' => DeliveryKind::RenewalReminder,
                    'series' => $series,
                    'series_year' => $renewalYear,
                    'member_id' => $member->getKey(),
                ],
                [
                    'email' => $member->email,
                    'payload' => [
                        'series' => $series,
                        'renewal_year' => $renewalYear,
                        'message' => (string) $message,
                    ],
                    'state' => DeliveryState::Pending,
                    'next_attempt_at' => now(),
                ],
            );

            if ($row->wasRecentlyCreated) {
                $names[] = $member->fullName();
            }
        }

        return $names;
    }
}
