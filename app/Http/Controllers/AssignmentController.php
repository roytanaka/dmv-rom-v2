<?php

namespace App\Http\Controllers;

use App\Http\Requests\BulkDeleteAssignmentRequest;
use App\Http\Requests\BulkStoreAssignmentRequest;
use App\Http\Requests\StoreAssignmentRequest;
use App\Models\Schedule;
use App\Models\Shift;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;

/**
 * Officer assignment write seam (#359, #363, PRD #352, ADR-0021 §Sign-up) — a Scheduler placing
 * a named Member on a Shift. This is a *distinct actor* from the self-service Sign-up on
 * {@see SignUpController} (a Scheduler seating someone else, not the authenticated user
 * seating themselves), so it is a separate seam — but it writes the **same** ordinary Sign-up
 * row: there is no second entity and no distinguishing column (ADR-0021 §Sign-up, point 2 of
 * #334). Single removal is not here — a Scheduler clears a placed seat through the ordinary
 * drop seam ({@see SignUpController::destroy}), whose ownership-gated email keeps an officer
 * removal silent.
 *
 * The Form Request has already cleared the schedule-admin gate, both sign-up floors on the
 * placed Member, capacity, and the one-seat rule, so a single placement is a single insert.
 * The bulk pair ({@see bulkStore}, {@see bulkDestroy}) is the labour-saver that retires
 * Reception's fortnight: a loop over the Shifts a filter names, N single writes plus a report,
 * adding no schema — the interval lives in the form, never in a row. No email fires for any of
 * these: the app does not tell a Scheduler what she just did.
 */
class AssignmentController extends Controller
{
    /**
     * Place the named Member on the route-bound Shift, writing an ordinary Sign-up row. The
     * Shift comes from the route; the seated Member is the validated `member_id`.
     */
    public function store(StoreAssignmentRequest $request, Shift $shift): RedirectResponse
    {
        $shift->signUps()->create(['member_id' => $request->integer('member_id')]);

        return back();
    }

    /**
     * Bulk-place a Member across a Schedule's Shifts in one form run (#363, ADR-0021 §5) — one
     * Sign-up on every Shift the filter names (days of week + wall-clock time, in the range, on
     * the interval's weeks). This is N single writes plus a report — never all-or-nothing. The
     * per-row rules bind here: a Shift the Member already holds a seat on, and a Shift already
     * at capacity, are each skipped and reported, and the rest are still written. The interval
     * (weekly or biweekly) and its anchor live in the form; no pattern is stored.
     */
    public function bulkStore(BulkStoreAssignmentRequest $request, Schedule $schedule): RedirectResponse
    {
        $data = $request->validated();
        $memberId = (int) $data['member_id'];

        $created = 0;
        $skipped = [];

        foreach ($this->matchingShifts($schedule, $data) as $shift) {
            if ($shift->signUps()->where('member_id', $memberId)->exists()) {
                $skipped[] = [
                    'shift_id' => $shift->id,
                    'reason' => 'group.scheduling_panel.bulk.skipped_already_signed_up',
                ];

                continue;
            }

            if ($shift->signUps()->count() >= $shift->capacity) {
                $skipped[] = [
                    'shift_id' => $shift->id,
                    'reason' => 'group.scheduling_panel.bulk.skipped_full',
                ];

                continue;
            }

            $shift->signUps()->create(['member_id' => $memberId]);
            $created++;
        }

        return back()->with('assignmentsBulk', ['created' => $created, 'skipped' => $skipped]);
    }

    /**
     * Bulk-remove a Member's Sign-ups on the same filter that placed them (#363, ADR-0021 §5)
     * — the symmetric undo, so a placed regular who stops coming is not stranded. Each matching
     * Shift the Member holds a seat on is cleared; a matching Shift they never held is simply
     * not counted. Removal answers only to the schedule-admin gate, never the sign-up floors,
     * so a Scheduler can clear a member who is by now on leave or resigned.
     */
    public function bulkDestroy(BulkDeleteAssignmentRequest $request, Schedule $schedule): RedirectResponse
    {
        $data = $request->validated();
        $memberId = (int) $data['member_id'];

        $removed = 0;

        foreach ($this->matchingShifts($schedule, $data) as $shift) {
            $signUp = $shift->signUps()->where('member_id', $memberId)->first();

            if ($signUp === null) {
                continue;
            }

            $signUp->delete();
            $removed++;
        }

        return back()->with('assignmentsBulk', ['removed' => $removed, 'skipped' => []]);
    }

    /**
     * The Shifts a bulk filter names: those inside the date range whose weekday and wall-clock
     * start / end times match, kept only on the interval's weeks. Resolved in PHP so no
     * comparison depends on the DB engine (the standards' warning on date handling), and
     * ordered by start so the report reads chronologically.
     *
     * @param  array<string, mixed>  $data
     * @return Collection<int, Shift>
     */
    private function matchingShifts(Schedule $schedule, array $data): Collection
    {
        $days = $data['days_of_week'];
        $zone = config('app.org_timezone');

        // Every date in this filter is a museum wall-clock date: the Scheduler picked
        // Tuesdays at 9am, not Tuesdays at 9am UTC. The range bounds are built org-local
        // and converted for the query; each candidate is read back org-local before its
        // weekday, time and week are compared. Matching on the stored UTC representation
        // would silently miss every evening Shift, which is a different day in UTC.
        $from = CarbonImmutable::parse($data['from_date'], $zone)->startOfDay()->utc();
        $to = CarbonImmutable::parse($data['to_date'], $zone)->endOfDay()->utc();
        $anchorWeek = CarbonImmutable::parse($data['anchor_date'], $zone)->startOfWeek();

        return $schedule->shifts()
            ->whereBetween('starts_at', [$from, $to])
            ->orderBy('starts_at')
            ->get()
            ->filter(function (Shift $shift) use ($days, $data, $anchorWeek, $zone): bool {
                $startsAt = $shift->starts_at->setTimezone($zone);
                $endsAt = $shift->ends_at->setTimezone($zone);

                return in_array($startsAt->dayOfWeek, $days, true)
                    && $startsAt->format('H:i') === $data['starts_time']
                    && $endsAt->format('H:i') === $data['ends_time']
                    && $this->onInterval($startsAt, $data['interval'], $anchorWeek);
            });
    }

    /**
     * Whether a Shift's week is selected by the interval. `weekly` takes every week; `biweekly`
     * takes alternating weeks, counting whole weeks from the anchor's week — an even count is
     * on, an odd count is off. The anchor is a form input, never stored (ADR-0021 §5). The
     * Shift's `starts_at` is reduced to its date before taking the week, so the count never
     * depends on the time of day. It arrives already on the organization's wall clock, so
     * an evening Shift is counted in the week the museum ran it, not the next one.
     */
    private function onInterval(DateTimeInterface $startsAt, string $interval, CarbonImmutable $anchorWeek): bool
    {
        if ($interval === 'weekly') {
            return true;
        }

        $shiftWeek = CarbonImmutable::instance($startsAt)->startOfDay()->startOfWeek();
        $weeks = (int) round(abs($anchorWeek->diffInDays($shiftWeek)) / 7);

        return $weeks % 2 === 0;
    }
}
