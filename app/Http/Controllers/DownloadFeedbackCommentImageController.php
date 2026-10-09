<?php

namespace App\Http\Controllers;

use App\Models\FeedbackCommentImage;
use App\Policies\FeedbackCommentImagePolicy;
use App\Support\FeedbackScreenshotStorage;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Serve one image on a Feedback comment (#778, ADR-0029 §8, §9), the comment's twin of
 * {@see DownloadFeedbackScreenshotController}. The item page and its image viewer load it
 * inline; `?download=1` saves it as an attachment with its original filename. Every
 * request passes a policy check, as the hard rules require
 * ({@see FeedbackCommentImagePolicy}). Only a PNG, JPEG, WebP, or GIF goes inline, and
 * every response sends `nosniff` ({@see FeedbackScreenshotStorage::response()}).
 *
 * No access log, for the reason on the screenshot controller.
 *
 * The same two-layer environment boundary as {@see FeedbackController}: the route is
 * registered only outside production, and this middleware returns 404 in production.
 */
class DownloadFeedbackCommentImageController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            fn (Request $request, Closure $next) => app()->environment('production')
                ? abort(404)
                : $next($request),
        ];
    }

    public function __invoke(Request $request, FeedbackCommentImage $feedbackCommentImage, FeedbackScreenshotStorage $storage): StreamedResponse
    {
        Gate::authorize('download', $feedbackCommentImage);

        return $storage->response($feedbackCommentImage, $request->boolean('download'));
    }
}
