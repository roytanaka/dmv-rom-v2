<?php

namespace App\Http\Controllers;

use App\Models\FeedbackScreenshot;
use App\Policies\FeedbackScreenshotPolicy;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Download one Feedback screenshot (#678, ADR-0029 §9) with its original filename. The
 * item page shows each screenshot through this route. Every download passes a policy
 * check, as the hard rules require ({@see FeedbackScreenshotPolicy}).
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

    public function __invoke(FeedbackScreenshot $feedbackScreenshot): StreamedResponse
    {
        Gate::authorize('download', $feedbackScreenshot);

        $disk = Storage::disk('local');

        abort_unless($disk->exists($feedbackScreenshot->storage_path), 404);

        return $disk->download($feedbackScreenshot->storage_path, $feedbackScreenshot->original_filename, [
            'Content-Type' => $feedbackScreenshot->mime_type,
        ]);
    }
}
