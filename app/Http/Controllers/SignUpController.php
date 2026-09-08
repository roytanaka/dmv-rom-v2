<?php

namespace App\Http\Controllers;

use App\Enums\DeliveryKind;
use App\Enums\DeliveryState;
use App\Http\Requests\DeleteSignUpRequest;
use App\Http\Requests\RecordSignUpVisitorsRequest;
use App\Http\Requests\StoreSignUpRequest;
use App\Models\Delivery;
use App\Models\Member;
use App\Models\Shift;
use App\Models\SignUp;
use Illuminate\Http\RedirectResponse;

/**
 * Sign-up write seam (#357, PRD #352, ADR-0021 §Sign-up) — a Member taking a Shift and
 * dropping it, from the same place. Both mutations are structurally authorized in their Form
 * Request, which delegates to the SignUpPolicy (the two floors and the Shift's `audience` on
 * the way in; owning the seat on the way out). A Scheduler *placing* a named Member is a
 * separate seam ({@see AssignmentController}, #359); a Scheduler *removing* one reuses
 * {@see destroy} below — the SignUpPolicy's `delete` also admits a schedule admin — and the
 * ownership-gated email keeps that officer removal silent.
 *
 * A self-service Sign-up records nothing about who created it — there is no provenance column
 * (point 2 of #334) — so the controller seats the authenticated user and nothing more.
 */
class SignUpController extends Controller
{
    /**
     * Take a Shift. The Shift comes from the route; the seat is the authenticated Member.
     * The Form Request has already cleared the floors, the `audience`, capacity, and the
     * one-seat rule, so this is a single insert.
     */
    public function store(StoreSignUpRequest $request, Shift $shift): RedirectResponse
    {
        $shift->signUps()->create(['member_id' => $request->user()->getKey()]);

        return back();
    }

    /**
     * Drop a Shift — cancelling the Sign-up. Permitted at any time up to (and past) the
     * Shift's start; the guard is ownership, resolved in the Form Request.
     *
     * A Member cancelling is **the single event where otherwise nobody finds out until the
     * shift is empty**, so every Scheduler and Chair of the owning Group is told —
     * unconditionally, with no threshold or proximity window (#358, ADR-0021 §Sign-up
     * "Notification"). The Notice fires only for a Member dropping their *own* seat: a
     * Scheduler removing a placed Member (officer removal, #359) is a distinct actor who is
     * already in contact with the person, and that silence is deliberate. Guarding on
     * ownership here keeps that silence true now that officer removal reuses this seam.
     *
     * Nothing sends in the request (#481, ADR-0024). The controller writes one Notice
     * {@see Delivery} per recipient and returns; the every-minute Drain sends them. The
     * Sign-up is gone by drain time, so the row carries a payload snapshot of everything the
     * mail names — the dropped Member, the Group, and the Shift's Schedule, times, and kind.
     */
    public function destroy(DeleteSignUpRequest $request, SignUp $signUp): RedirectResponse
    {
        $isSelfCancellation = $signUp->member_id === $request->user()->getKey();

        $signUp->loadMissing('shift.kind', 'shift.schedule.group', 'member');
        $shift = $signUp->shift;
        $member = $signUp->member;

        $signUp->delete();

        if ($isSelfCancellation) {
            $snapshot = $this->cancellationSnapshot($shift, $member);

            foreach ($shift->schedule->group->schedulers() as $scheduler) {
                Delivery::create([
                    'kind' => DeliveryKind::Notice,
                    'member_id' => $scheduler->getKey(),
                    'email' => $scheduler->email,
                    'payload' => $snapshot,
                    'state' => DeliveryState::Pending,
                    'next_attempt_at' => now(),
                ]);
            }
        }

        return back();
    }

    /**
     * Freeze what a cancellation Notice names into a payload the Drain can render from without
     * the Sign-up, which is already gone. Shape mirrors {@see SignUpCancelled::fromSnapshot}.
     *
     * @return array<string, mixed>
     */
    private function cancellationSnapshot(Shift $shift, Member $member): array
    {
        return [
            'member' => [
                'first_name' => $member->first_name,
                'last_name' => $member->last_name,
            ],
            'group' => [
                'name' => $shift->schedule->group->name,
                'slug' => $shift->schedule->group->slug,
            ],
            'schedule' => [
                'id' => $shift->schedule->id,
                'name' => $shift->schedule->name,
            ],
            'shift' => [
                'starts_at' => $shift->starts_at->toIso8601String(),
                'ends_at' => $shift->ends_at->toIso8601String(),
                'kind' => $shift->kind?->name,
            ],
        ];
    }

    /**
     * Record the after-the-shift numbers on a Sign-up (#445, PRD #443, ADR-0023 §5). One seam
     * for the whole feature: the inline Agenda panel, and later the outstanding panel and the
     * Officer's correction, all PATCH here. The Form Request has already resolved the policy
     * (the seat-holder's own seat, inside the window) and whitelisted the fields — whose seat
     * is written comes from the route binding, never the body — so this fills and saves.
     */
    public function record(RecordSignUpVisitorsRequest $request, SignUp $signUp): RedirectResponse
    {
        $signUp->record($request->validated());

        return back();
    }
}
