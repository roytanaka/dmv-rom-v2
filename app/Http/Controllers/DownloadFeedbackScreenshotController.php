<?php

namespace App\Http\Controllers;

use App\Models\FeedbackScreenshot;
use App\Policies\FeedbackScreenshotPolicy;
use App\Support\FeedbackScreenshotStorage;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Serve one Feedback screenshot (#678, #776, ADR-0029 §9). The item page and its image
 * viewer load it inline; `?download=1` saves it as an attachment with its original
 * filename. Every request passes a policy check, as the hard rules require
 * ({@see FeedbackScreenshotPolicy}).
 *
 * Only a PNG, JPEG, WebP, or GIF goes inline, and every response sends `nosniff`
 * ({@see FeedbackScreenshotStorage::response()}).
 *
 * No access log, unlike the Documents download flow (docs/conventions.md § Documents): the
 * item page loads every screenshot as an image, so a log line would record page views, and
 * a Tester's screenshot is not Member data.
 *
 * The same two-layer environment boundary as {@see FeedbackController}: the route is
 * registered only outside production, and this middleware returns 404 in production.
 */
class DownloadFeedbackScreenshotController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            fn (Request $request, Closure $next) => app()->environment('production')
                ? abort(404)
                : $next($request),
        ];
    }

    public function __invoke(Request $request, FeedbackScreenshot $feedbackScreenshot, FeedbackScreenshotStorage $storage): StreamedResponse
    {
        Gate::authorize('download', $feedbackScreenshot);

        return $storage->response($feedbackScreenshot, $request->boolean('download'));
    }
}
