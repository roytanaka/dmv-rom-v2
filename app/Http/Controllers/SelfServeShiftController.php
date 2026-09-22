<?php

namespace App\Http\Controllers;

use App\Enums\ShiftAudience;
use App\Http\Requests\DeleteSelfServeShiftRequest;
use App\Http\Requests\StoreSelfServeShiftRequest;
use App\Http\Requests\UpdateSelfServeShiftRequest;
use App\Models\Schedule;
use App\Models\Shift;
use App\Support\Notices\SignUpCancellationNoticeWriter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

/**
 * Self-serve Shift write seam (#585, PRD #576, ADR-0026 §1, §2) — a Gallery Interpreter
 * writing, changing, and deleting their own Shift. Distinct from the Scheduler-only
 * {@see ShiftController}: here a Member authors a record about themselves, so a store writes
 * the Shift *and* their Sign-up in one transaction, and update/delete answer to the derived
 * ownership rule of the ShiftPolicy rather than the schedule-admin gate.
 *
 * The Member picks a station, a start, and a count of **units**; `ends_at` is derived from the
 * start, the units, and the Group's per-unit length ({@see Shift::deriveEndsAt}) and the unit
 * count is never stored (ADR-0026 §2). The Shift is always capacity 1, audience `group` — a
 * wider slot or an `open` audience is a Scheduler affordance a Member never reaches. Objects
 * and the clash checks follow in later tickets (#586, #588).
 */
class SelfServeShiftController extends Controller
{
    /**
     * Write a self-serve Shift and its author's Sign-up in one transaction. The station and
     * units come from the Form Request whitelist; `ends_at` is derived; capacity and audience
     * are fixed. The two rows are written together so a Shift never exists without its seat.
     */
    public function store(StoreSelfServeShiftRequest $request, Schedule $schedule): RedirectResponse
    {
        $data = $request->validated();

        $endsAt = Shift::deriveEndsAt(
            $request->date('starts_at'),
            (int) $data['units'],
            $schedule->group->self_serve_unit_minutes,
        );

        DB::transaction(function () use ($request, $schedule, $data, $endsAt) {
            $shift = $schedule->shifts()->create([
                'starts_at' => $data['starts_at'],
                'ends_at' => $endsAt,
                'capacity' => 1,
                'shift_kind_id' => $data['shift_kind_id'],
                'audience' => ShiftAudience::Group,
            ]);

            $shift->signUps()->create(['member_id' => $request->user()->getKey()]);
        });

        return back();
    }

    /**
     * Change a self-authored Shift — its station, start, or units — re-deriving `ends_at`. Only
     * the Shift's own fields move; the Sign-up (the author's seat) is untouched. Capacity and
     * audience are left as they are (1 / `group`, by construction of the store).
     */
    public function update(UpdateSelfServeShiftRequest $request, Shift $shift): RedirectResponse
    {
        $data = $request->validated();

        $endsAt = Shift::deriveEndsAt(
            $request->date('starts_at'),
            (int) $data['units'],
            $shift->schedule->group->self_serve_unit_minutes,
        );

        $shift->update([
            'starts_at' => $data['starts_at'],
            'ends_at' => $endsAt,
            'shift_kind_id' => $data['shift_kind_id'],
        ]);

        return back();
    }

    /**
     * Delete a self-authored Shift, dropping the author's Sign-up with it (ADR-0026 §1). The two
     * rows go together in one transaction, and the same cancellation Notice a drop fires is
     * written to every Scheduler and Chair of the Group ({@see SignUpCancellationNoticeWriter}),
     * so a self-serve delete reads to the schedule admins exactly as a drop does. The Shift is
     * loaded with what the Notice names before it is removed.
     */
    public function destroy(DeleteSelfServeShiftRequest $request, Shift $shift, SignUpCancellationNoticeWriter $notices): RedirectResponse
    {
        $shift->loadMissing('kind', 'schedule.group', 'signUps');
        $member = $request->user();

        DB::transaction(function () use ($shift) {
            $shift->signUps()->delete();
            $shift->delete();
        });

        $notices->write($shift, $member);

        return back();
    }
}
