<?php

namespace App\Http\Middleware;

use App\Http\Controllers\ImpersonationController;
use App\Models\Member;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Only the Current Category group may stay signed in (ADR-0038 §4, spec #913). Sign-in already
 * refuses everyone else; this closes a session that was open when the Member left Current, and a
 * Remember me cookie that would restore one. The next request signs them out.
 *
 * A support operator's impersonation session is left alone (ADR-0009): the real person is the
 * operator, and the switcher's Negative Personas are not Current on purpose.
 */
class EnsureMemberIsCurrent
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Member|null $member */
        $member = $request->user();

        if ($member === null || $member->isCurrent() || $request->session()->has(ImpersonationController::OPERATOR_KEY)) {
            return $next($request);
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
