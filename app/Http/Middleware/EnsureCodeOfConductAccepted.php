<?php

namespace App\Http\Middleware;

use App\Models\Member;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Steps 2 to 4 of the Renewal (ADR-0038 §9) open only once the Member has accepted the code of
 * conduct for the year the Renewal serves; otherwise back to step 1. A Member who need not renew
 * passes through, so the step's request refuses them as before.
 */
class EnsureCodeOfConductAccepted
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Member $member */
        $member = $request->user();

        if ($member->renewalEligibility()->eligible() && ! $member->acceptedCodeOfConduct()) {
            return to_route('renew');
        }

        return $next($request);
    }
}
