<?php

namespace App\Http\Controllers;

use App\Http\Requests\DeleteFeedbackCommentRequest;
use App\Http\Requests\DeleteFeedbackItemRequest;
use App\Http\Requests\UpdateFeedbackStatusRequest;
use App\Models\FeedbackComment;
use App\Models\FeedbackCommentImage;
use App\Models\FeedbackItem;
use App\Models\Member;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * The Support-operator's triage of Tester feedback (#679, ADR-0029 §7): set an item's
 * status, delete an item with its comments and images, and delete one comment with its
 * images.
 *
 * Each action's Form Request authorizes it (ADR-0017 §4) by
 * {@see Member::isSupportOperator()} on the request's Member, read directly and never
 * through a Gate (§5): `Gate::before` grants super-tier every ability, and the President
 * must not get the maintainer's triage. While the operator impersonates, the request's
 * Member is the Persona, so the Persona's answer applies.
 *
 * The same two-layer environment boundary as {@see FeedbackController}: the routes are
 * registered only outside production, and this middleware returns 404 in production,
 * before the Form Request resolves.
 */
class FeedbackTriageController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            fn (Request $request, Closure $next) => app()->environment('production')
                ? abort(404)
                : $next($request),
        ];
    }

    public function updateStatus(UpdateFeedbackStatusRequest $request, FeedbackItem $feedbackItem): RedirectResponse
    {
        $feedbackItem->update(['status' => $request->validated('status')]);

        return back();
    }

    /**
     * Delete an item, its comments, its screenshots, and its comments' images (#778): the
     * rows and the files. The rows go in one transaction on the feedback connection. The
     * deletes are explicit, not left to the foreign keys' cascade, so SQLite in the tests
     * behaves like MariaDB. The files go after the commit, so a failed delete never leaves
     * rows without their files.
     */
    public function destroy(DeleteFeedbackItemRequest $request, FeedbackItem $feedbackItem): RedirectResponse
    {
        $paths = [
            ...$feedbackItem->screenshots()->pluck('storage_path'),
            ...$feedbackItem->commentImages()->pluck('storage_path'),
        ];

        DB::connection('feedback')->transaction(function () use ($feedbackItem) {
            FeedbackCommentImage::whereIn('feedback_comment_id', $feedbackItem->comments()->select('id'))->delete();
            $feedbackItem->comments()->delete();
            $feedbackItem->screenshots()->delete();
            $feedbackItem->delete();
        });

        Storage::disk('local')->delete($paths);

        return to_route('feedback');
    }

    /**
     * Delete one comment with its images (#778): the rows in one transaction, then the
     * files, as for an item. The comment must sit under the item in the URL.
     */
    public function destroyComment(DeleteFeedbackCommentRequest $request, FeedbackItem $feedbackItem, FeedbackComment $feedbackComment): RedirectResponse
    {
        abort_unless($feedbackComment->feedback_item_id === $feedbackItem->id, 404);

        $paths = $feedbackComment->images()->pluck('storage_path')->all();

        DB::connection('feedback')->transaction(function () use ($feedbackComment) {
            $feedbackComment->images()->delete();
            $feedbackComment->delete();
        });

        Storage::disk('local')->delete($paths);

        return back();
    }
}
