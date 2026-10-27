<?php

namespace App\Http\Controllers;

use App\Models\FeedbackFollower;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Stop updates (#857, spec #856, ADR-0029 §15): the page behind the signed link in every
 * Follower email. The `signed` middleware checks the link; there is no login, so the Tester
 * can stop from their phone, and a stranger who got the emails through a typo can too.
 *
 * GET shows one "Stop updates" button and changes nothing, because mail scanners open links.
 * POST deletes the one Follower, so the address keeps every other item it follows. A link
 * whose Follower or item is already gone says the updates are already stopped: not an error.
 * The page names the item but never the address.
 *
 * The same two-layer environment boundary as {@see FeedbackController}.
 */
class FeedbackUnfollowController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            fn (Request $request, Closure $next) => app()->environment('production')
                ? abort(404)
                : $next($request),
        ];
    }

    public function show(Request $request, string $follower): Response
    {
        $found = $this->find($follower);

        return $this->page($found === null ? 'already' : 'confirm', $found, $request);
    }

    public function destroy(Request $request, string $follower): Response
    {
        $found = $this->find($follower);

        $found?->delete();

        return $this->page($found === null ? 'already' : 'stopped', $found, $request);
    }

    private function find(string $id): ?FeedbackFollower
    {
        return FeedbackFollower::with('feedbackItem')->whereHas('feedbackItem')->find($id);
    }

    private function page(string $state, ?FeedbackFollower $follower, Request $request): Response
    {
        return Inertia::render('feedback/StopUpdates', [
            'state' => $state,
            'itemLabel' => $follower?->feedbackItem->label(),
            'stopHref' => $request->fullUrl(),
        ]);
    }
}
