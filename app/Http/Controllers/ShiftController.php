<?php

namespace App\Http\Controllers;

use App\Http\Requests\BulkDeleteShiftRequest;
use App\Http\Requests\BulkStoreShiftRequest;
use App\Http\Requests\DeleteShiftRequest;
use App\Http\Requests\StoreShiftRequest;
use App\Http\Requests\UpdateShiftRequest;
use App\Models\Schedule;
use App\Models\Shift;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Http\RedirectResponse;

/**
 * Shift authoring (#356, PRD #352, ADR-0021 §2) — the Scheduler's write seam for the
 * Shifts on a Schedule, parallel to the Schedule write seam on
 * {@see ScheduleController}. Every mutation is structurally authorized in its Form
 * Request, which delegates to the ShiftPolicy — the same schedule-admin gate the
 * SchedulePolicy uses (a Scheduler or Chair of the owning Group, plus the super-tier,
 * only while the Group's scheduling capability is on).
 *
 * The rules make bad states unreachable rather than unwound: a Shift's times are held
 * inside its Schedule's date range, and deletion is cancelling — never silent.
 */
class ShiftController extends Controller
{
    /**
     * Add a Shift to a Schedule. The owning Schedule comes from the route; the fields
     * come from the Form Request whitelist. Adding is purely additive, so it is
     * permitted even on a `published` Schedule (ADR-0021 §2). `capacity` and `audience`
     * fall to the database defaults (1, `group`) when absent.
     */
    public function store(StoreShiftRequest $request, Schedule $schedule): RedirectResponse
    {
        $schedule->shifts()->create($request->validated());

        return back();
    }

    /**
     * Edit a Shift — its times, capacity, kind or audience. Raising capacity is a single
     * update that disturbs nothing; edited times stay inside the Schedule's range. The
     * owning Schedule is fixed at creation.
     */
    public function update(UpdateShiftRequest $request, Shift $shift): RedirectResponse
    {
        $shift->update($request->validated());

        return back();
    }

    /**
     * Delete a Shift — cancelling it. Permitted only at zero Sign-ups once Sign-ups
     * exist (#357); cancelling is never silent.
     */
    public function destroy(DeleteShiftRequest $request, Shift $shift): RedirectResponse
    {
        $shift->delete();

        return back();
    }

    /**
     * Bulk-create a month of Shifts in one form run (#362, ADR-0021 §2): one Shift on
     * every matching weekday in the range, at the given wall-clock times. This is N single
     * writes plus a report — never all-or-nothing. A day whose Shift would fall outside
     * the Schedule's date range is skipped and reported (the range is honoured per row),
     * and the rest are still written. It adds no stored pattern: the days of week and the
     * range live in the form, never in a row.
     */
    public function bulkStore(BulkStoreShiftRequest $request, Schedule $schedule): RedirectResponse
    {
        $data = $request->validated();
        $days = $data['days_of_week'];
        $zone = config('app.org_timezone');

        $created = 0;
        $skipped = [];

        // The fan-out runs on the museum's wall clock and only converts at the end. The
        // range days, the weekday each one falls on, and the stamped time are all org-local;
        // fanning out in UTC instead would stamp a 9am Shift as 9am UTC (5am at the museum),
        // and push an evening Shift onto the following weekday.
        $from = CarbonImmutable::parse($data['from_date'], $zone)->startOfDay();
        $to = CarbonImmutable::parse($data['to_date'], $zone)->startOfDay();

        foreach (CarbonPeriod::create($from, $to) as $day) {
            if (! in_array($day->dayOfWeek, $days, true)) {
                continue;
            }

            $startsAt = CarbonImmutable::instance($day)->setTimeFromTimeString($data['starts_time'])->utc();
            $endsAt = CarbonImmutable::instance($day)->setTimeFromTimeString($data['ends_time'])->utc();

            if (! $schedule->coversInterval($startsAt, $endsAt)) {
                $skipped[] = [
                    'date' => $day->toDateString(),
                    'reason' => 'group.scheduling_panel.bulk.skipped_outside_range',
                ];

                continue;
            }

            $schedule->shifts()->create([
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'capacity' => $data['capacity'] ?? 1,
                'shift_kind_id' => $data['shift_kind_id'] ?? null,
            ]);
            $created++;
        }

        return back()->with('shiftsBulk', ['created' => $created, 'skipped' => $skipped]);
    }

    /**
     * Bulk-delete the Shifts matching the same filter that created them (#362, ADR-0021 §2)
     * — legacy's skip-dates job without a field. A Shift matches when its weekday, wall-clock
     * times, capacity and kind all match the filter and it falls in the range. Each match
     * honours the zero-Sign-ups delete rule per row: one with Members on it is skipped and
     * named, and the batch removes the rest.
     */
    public function bulkDestroy(BulkDeleteShiftRequest $request, Schedule $schedule): RedirectResponse
    {
        $data = $request->validated();
        $days = $data['days_of_week'];
        $capacity = $data['capacity'] ?? 1;
        $kindId = $data['shift_kind_id'] ?? null;
        $zone = config('app.org_timezone');

        // The filter is read on the same wall clock that wrote it: the range bounds are
        // org-local days converted to instants for the query, and each candidate's weekday
        // and time are read org-local before they are compared. Comparing the stored UTC
        // representation instead would match nothing for any Group whose Shifts cross the
        // UTC day boundary — the museum's whole evening.
        $from = CarbonImmutable::parse($data['from_date'], $zone)->startOfDay()->utc();
        $to = CarbonImmutable::parse($data['to_date'], $zone)->endOfDay()->utc();

        $matches = $schedule->shifts()
            ->whereBetween('starts_at', [$from, $to])
            ->orderBy('starts_at')
            ->get()
            ->filter(function (Shift $shift) use ($days, $data, $capacity, $kindId, $zone): bool {
                $startsAt = $shift->starts_at->setTimezone($zone);
                $endsAt = $shift->ends_at->setTimezone($zone);

                return in_array($startsAt->dayOfWeek, $days, true)
                    && $startsAt->format('H:i') === $data['starts_time']
                    && $endsAt->format('H:i') === $data['ends_time']
                    && $shift->capacity === $capacity
                    && $shift->shift_kind_id === $kindId;
            });

        $deleted = 0;
        $skipped = [];

        foreach ($matches as $shift) {
            if ($shift->signUps()->exists()) {
                $skipped[] = [
                    'shift_id' => $shift->id,
                    'reason' => 'group.scheduling_panel.bulk.skipped_has_sign_ups',
                ];

                continue;
            }

            $shift->delete();
            $deleted++;
        }

        return back()->with('shiftsBulk', ['deleted' => $deleted, 'skipped' => $skipped]);
    }
}
