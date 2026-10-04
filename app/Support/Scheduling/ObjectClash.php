<?php

namespace App\Support\Scheduling;

use App\Models\HandlingObject;
use App\Models\Shift;
use App\Models\SignUp;
use Illuminate\Support\Collection;

/**
 * The Object double-booking block (#586, ADR-0026 §3) — one reusable check, called from all four
 * write seams (a self-serve store and update, a take, and an officer placement). An Object cannot
 * be in two places at once: for each Object a Sign-up would reserve, any *other* Sign-up carrying
 * that Object whose {@see ObjectHold} overlaps the candidate's is a clash.
 *
 * Legacy ran this check three ways in the browser and once more against events; this replaces all
 * four with one overlap pass, run on the server where legacy's was not (the security half of the
 * faithful-port rule). Resolved in PHP against the loaded holds so it never depends on the DB
 * engine — the widened off-site hold (#587) is not expressible as a single time-range column
 * comparison, so the candidate set is gathered by Object and the overlap is decided in code.
 */
final class ObjectClash
{
    /**
     * The clashes a candidate Shift would create by reserving the given Objects. Each entry names
     * the clashing Object and the other Shift whose hold it collides with, so the caller can name
     * both in the error. The actor's own Sign-up is excluded on an edit ({@see $excludeSignUpId}),
     * so keeping the same Object is not a clash with oneself.
     *
     * @param  list<int>  $objectIds
     * @return Collection<int, array{object: HandlingObject, shift: Shift}>
     */
    public static function against(Shift $candidate, array $objectIds, ?int $excludeSignUpId = null): Collection
    {
        if ($objectIds === []) {
            return collect();
        }

        $hold = $candidate->objectHold();

        // Every Sign-up carrying any of the candidate Objects, its Objects and Shift loaded so the
        // overlap and the name read without a further query. Objects are Group-scoped, so a shared
        // Object id already confines this to the candidate's Group.
        $reservations = SignUp::query()
            ->whereHas('objects', fn ($query) => $query->whereIn('objects.id', $objectIds))
            ->when($excludeSignUpId !== null, fn ($query) => $query->whereKeyNot($excludeSignUpId))
            ->with(['objects', 'shift.kind'])
            ->get();

        $clashes = collect();

        foreach ($reservations as $signUp) {
            if (! $hold->overlaps($signUp->shift->objectHold())) {
                continue;
            }

            foreach ($signUp->objects as $object) {
                if (in_array($object->getKey(), $objectIds, true)) {
                    $clashes->push(['object' => $object, 'shift' => $signUp->shift]);
                }
            }
        }

        // One line per clashing Object — the first colliding Shift names it; a second collision on
        // the same Object adds nothing the Member needs to pick again.
        return $clashes->unique(fn (array $clash) => $clash['object']->getKey())->values();
    }

    /**
     * The station clash warning (#588, ADR-0026 §5) — whether another Sign-up already sits on a
     * Shift of the candidate's kind at an overlapping time. Unlike the Object hold, the window is
     * the Shift's own `[starts_at, ends_at)` interval: two seats on one station overlap when their
     * ranges do, half-open on the end so back-to-back seats do not clash. All kinds are checked;
     * there is no skip list. The candidate's own Shift is excluded on an edit ({@see $excludeShiftId}).
     *
     * A soft warning, not a block: two GIs may share a gallery on purpose, so the caller offers an
     * acknowledgement. The interval is a plain time-range column comparison — no widened hold to
     * resolve — so the overlap is decided in the query and never depends on the DB engine. A shared
     * kind id already confines this to the candidate's Group, since kinds are Group-scoped.
     */
    public static function station(Shift $candidate, ?int $excludeShiftId = null): bool
    {
        if ($candidate->shift_kind_id === null) {
            return false;
        }

        return SignUp::query()
            ->whereHas('shift', fn ($query) => $query
                ->where('shift_kind_id', $candidate->shift_kind_id)
                ->when($excludeShiftId !== null, fn ($query) => $query->whereKeyNot($excludeShiftId))
                ->where('starts_at', '<', $candidate->ends_at)
                ->where('ends_at', '>', $candidate->starts_at))
            ->exists();
    }
}
