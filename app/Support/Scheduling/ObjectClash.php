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
}
