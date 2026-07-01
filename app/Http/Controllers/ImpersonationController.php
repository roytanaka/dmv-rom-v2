<?php

namespace App\Http\Controllers;

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Member;
use App\Personas\PersonaCatalogue;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * The dev/QA role-switcher's server half (ADR-0009 dev half, PRD #220): a hand-rolled
 * become/stop over {@see Auth::login()} — no impersonation package (rejected per
 * ADR-0009 and the rule of three). A super-tier operator becomes any catalogued
 * {@see PersonaCatalogue} Persona and always gets back to the original operator.
 *
 * The environment boundary is the whole security story, in two independent layers:
 *   1. the routes are registered only outside production (routes/web.php);
 *   2. every action here re-checks {@see abortIfProduction()} — so a future refactor
 *      that registers the routes everywhere still refuses in production.
 * No permission check is ever the sole guard; the tier check below is an inner
 * refinement within the non-prod boundary (env-and-permission combine with AND).
 */
class ImpersonationController extends Controller
{
    /**
     * Session key holding the original operator's id across an impersonation — the
     * thread back to the real super-tier human. Rebasing never overwrites it with an
     * intermediate Persona, so Stop always returns to the operator who started.
     *
     * Public so {@see HandleInertiaRequests} can read the same
     * key without duplicating the string.
     */
    public const OPERATOR_KEY = 'impersonator_id';

    /**
     * Become a catalogued Persona. Authorized for a super-tier operator, or for an
     * already-impersonating session (so a low-privilege Persona can rebase). Refuses
     * any account that is not a catalogued Persona (404).
     */
    public function start(Request $request): RedirectResponse
    {
        $this->abortIfProduction();

        $operator = $request->user();
        $originalOperatorId = $request->session()->get(self::OPERATOR_KEY);

        // Start authorization (ADR-0009): super-tier OR an impersonation session is
        // already active. The disjunction lets a currently-impersonated low-privilege
        // Persona rebase — genuinely authorized server-side, not merely shown.
        abort_unless(
            $operator->isAllDmv() || $originalOperatorId !== null,
            403,
        );

        // Allowlist: the switcher becomes only catalogued Personas (a bulk-pool row
        // or any other non-Persona account is refused as if it did not exist).
        $email = (string) $request->input('email');
        abort_unless(PersonaCatalogue::has($email), 404);

        $persona = Member::where('email', $email)->firstOrFail();

        // Single-level, rebase-not-stack: the remembered operator is always the
        // ORIGINAL super-tier human, never the intermediate Persona we may be on now.
        $operatorId = $originalOperatorId ?? $operator->getKey();

        Auth::login($persona);

        // Auth::login regenerates the session id; write the operator id AFTER the
        // login so it survives the regeneration — Stop reads it to find its way home.
        $request->session()->put(self::OPERATOR_KEY, $operatorId);

        return back();
    }

    /**
     * Return to the operator the impersonation started from and clear the thread.
     * Requires only an active impersonation session (any tier) — a low-privilege
     * Persona must never be stranded without a way back.
     */
    public function stop(Request $request): RedirectResponse
    {
        $this->abortIfProduction();

        $operatorId = $request->session()->get(self::OPERATOR_KEY);
        abort_unless($operatorId !== null, 403);

        Auth::login(Member::findOrFail($operatorId));
        $request->session()->forget(self::OPERATOR_KEY);

        return back();
    }

    /**
     * Layer two of the environment boundary (ADR-0009): even if a future refactor
     * registers the routes in production, the handler still refuses there. Layer one
     * is the non-prod-only route registration in routes/web.php.
     */
    private function abortIfProduction(): void
    {
        abort_if(app()->environment('production'), 404);
    }
}
