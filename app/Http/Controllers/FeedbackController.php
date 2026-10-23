<?php

namespace App\Http\Controllers;

use App\Enums\FeedbackStatus;
use App\Enums\FeedbackType;
use App\Http\Requests\StoreFeedbackCommentRequest;
use App\Http\Requests\StoreFeedbackItemRequest;
use App\Models\FeedbackComment;
use App\Models\FeedbackCommentImage;
use App\Models\FeedbackItem;
use App\Models\FeedbackScreenshot;
use App\Models\Member;
use App\Support\AppVersion;
use App\Support\FeedbackScreenshotStorage;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/**
 * Tester feedback (#676, #677, #678, ADR-0029): send a Feedback item with its screenshots,
 * list them on the Feedback page, filtered by type and status (#679), open one on its own page, and comment on it. Any
 * logged-in Member may do all of these (§4).
 *
 * The environment boundary has two layers, like the Role-switcher (ADR-0009):
 *   1. the routes are registered only outside production (routes/web.php);
 *   2. this controller's middleware returns 404 in production, before any validation,
 *      so a future refactor that registers the routes everywhere still refuses.
 */
class FeedbackController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            fn (Request $request, Closure $next) => app()->environment('production')
                ? abort(404)
                : $next($request),
        ];
    }

    /**
     * The Feedback page: every item, newest first, filtered by type and by status (§13),
     * each with its comment count (#701), counted in the one query. The status filter also
     * takes `open` and `closed`, each a group of statuses.
     * The filters are query parameters, so a filtered list has a URL. A value that names
     * no type or status is ignored. No policy check beyond `auth`: every logged-in Tester
     * reads every item (ADR-0029 §4).
     */
    public function index(Request $request): Response
    {
        $type = FeedbackType::tryFrom($this->queryString($request, 'type'));
        $statusFilter = $this->queryString($request, 'status');
        $status = FeedbackStatus::tryFrom($statusFilter);
        $open = match ($statusFilter) {
            'open' => true,
            'closed' => false,
            default => null,
        };

        $items = FeedbackItem::query()
            ->when($type, fn ($query) => $query->where('type', $type))
            ->when($status, fn ($query) => $query->where('status', $status))
            ->when($open !== null, fn ($query) => $query->whereIn('status', FeedbackStatus::grouped($open)))
            ->withCount(['comments', 'screenshots', 'commentImages'])
            ->latest()
            ->orderByDesc('id')
            ->get()
            ->map(fn (FeedbackItem $item) => [
                ...$this->summary($item),
                'excerpt' => Str::limit($item->message, 160),
                'commentsCount' => $item->comments_count,
                // Every image on the item: its screenshots and its comments' images (#780).
                'attachmentsCount' => $item->screenshots_count + $item->comment_images_count,
                'href' => route('feedback.show', $item, false),
            ]);

        return Inertia::render('feedback/Index', [
            'items' => $items,
            'filters' => ['type' => $type?->value, 'status' => $status?->value ?? ($open !== null ? $statusFilter : null)],
            'types' => $this->options(FeedbackType::cases()),
            'statuses' => $this->options(FeedbackStatus::cases()),
            'listHref' => route('feedback', [], false),
        ]);
    }

    /**
     * One Feedback item's page (§13): the message, its screenshots (§9), everything
     * captured with it, and the comments, oldest first (§8), each with its images (#778). Every logged-in Tester reads every item (§4).
     * Only the Support-operator sees the status picker and the delete controls (§7, #679).
     * `can.manage` reads the same direct predicate as the triage Form Requests.
     */
    public function show(Request $request, FeedbackItem $feedbackItem): Response
    {
        return Inertia::render('feedback/Show', [
            'item' => [
                ...$this->summary($feedbackItem),
                'message' => $feedbackItem->message,
                'pageUrl' => $feedbackItem->page_url,
                'pageHref' => $feedbackItem->pageHref(),
                'routeName' => $feedbackItem->route_name,
                'locale' => $feedbackItem->locale,
                'userAgent' => $feedbackItem->user_agent,
                'viewportWidth' => $feedbackItem->viewport_width,
                'viewportHeight' => $feedbackItem->viewport_height,
                'memberName' => $feedbackItem->member_name,
                'memberEmail' => $feedbackItem->member_email,
                'impersonatorName' => $feedbackItem->impersonator_name,
                'appVersion' => $feedbackItem->app_version,
            ],
            'screenshots' => $feedbackItem->screenshots()
                ->orderBy('id')
                ->get()
                ->map(fn (FeedbackScreenshot $screenshot) => $this->image($screenshot, 'feedback.screenshots.download')),
            'comments' => $feedbackItem->comments()
                ->with(['images' => fn ($query) => $query->orderBy('id')])
                ->oldest()
                ->orderBy('id')
                ->get()
                ->map(fn (FeedbackComment $comment) => [
                    'id' => $comment->id,
                    'testerName' => $comment->tester_name,
                    'body' => $comment->body,
                    'createdAt' => $comment->created_at->toIso8601String(),
                    'images' => $comment->images->map(fn (FeedbackCommentImage $image) => $this->image($image, 'feedback.comment-images.download')),
                    'deleteHref' => route('feedback.comments.destroy', [$feedbackItem, $comment], false),
                ]),
            'listHref' => route('feedback', [], false),
            'commentHref' => route('feedback.comments.store', $feedbackItem, false),
            'can' => ['manage' => $request->user()->isSupportOperator()],
            'statuses' => $this->options(FeedbackStatus::cases()),
            'statusHref' => route('feedback.status.update', $feedbackItem, false),
            'deleteHref' => route('feedback.destroy', $feedbackItem, false),
        ]);
    }

    /**
     * Add a comment under a Feedback item (§8). Any logged-in Member may comment; the
     * name is the Tester's typed one, like on a send. Comments are never edited. Its
     * images (#778) are stored with it, in one transaction on the feedback connection.
     */
    public function storeComment(StoreFeedbackCommentRequest $request, FeedbackItem $feedbackItem, FeedbackScreenshotStorage $images): RedirectResponse
    {
        DB::connection('feedback')->transaction(function () use ($request, $feedbackItem, $images) {
            $comment = $feedbackItem->comments()->create($request->safe()->except('images'));

            $images->store($comment->images(), $request->file('images', []));
        });

        return back();
    }

    /**
     * Store a Feedback item. The request carries what the Tester typed and the client
     * context; the server fills everything it can know itself (§10): the page's route,
     * the locale, the Member, the impersonator, the app version, and the time. Every
     * item starts as New. The screenshots (§9) are stored with it, in one transaction on
     * the feedback connection, so an item never shows half its screenshots.
     */
    public function store(StoreFeedbackItemRequest $request, FeedbackScreenshotStorage $screenshots): RedirectResponse
    {
        $member = $request->user();
        $operatorId = $request->session()->get(ImpersonationController::OPERATOR_KEY);

        DB::connection('feedback')->transaction(function () use ($request, $member, $operatorId, $screenshots) {
            $item = FeedbackItem::create([
                ...$request->safe()->except('screenshots'),
                'route_name' => $this->routeNameFor($request->validated('page_url')),
                'locale' => app()->getLocale(),
                'member_name' => $member->fullName(),
                'member_email' => $member->email,
                'impersonator_name' => $operatorId === null ? null : Member::find($operatorId)?->fullName(),
                'app_version' => AppVersion::label(),
            ]);

            $screenshots->store($item->screenshots(), $request->file('screenshots', []));
        });

        return back();
    }

    /**
     * One image on the item page, a screenshot or a comment's image: `href` loads it inline,
     * `downloadHref` saves it, both through `$route`, its policy-checked download route.
     *
     * @return array<string, mixed>
     */
    private function image(FeedbackScreenshot|FeedbackCommentImage $image, string $route): array
    {
        return [
            'id' => $image->id,
            'filename' => $image->original_filename,
            'sizeBytes' => $image->size_bytes,
            'mimeType' => $image->mime_type,
            'href' => route($route, $image, false),
            'downloadHref' => route($route, [$image, 'download' => 1], false),
        ];
    }

    /**
     * What the Feedback page row and the item page both show about an item.
     *
     * @return array<string, mixed>
     */
    private function summary(FeedbackItem $item): array
    {
        return [
            'id' => $item->id,
            'typeLabelKey' => $item->type->labelKey(),
            'status' => $item->status->value,
            'statusLabelKey' => $item->status->labelKey(),
            'testerName' => $item->tester_name,
            'createdAt' => $item->created_at->toIso8601String(),
        ];
    }

    /**
     * The picker options for a Feedback enum, in case order: each value with its label key.
     *
     * @param  list<FeedbackType>|list<FeedbackStatus>  $cases
     * @return list<array{value: string, labelKey: string}>
     */
    private function options(array $cases): array
    {
        return array_map(fn (FeedbackType|FeedbackStatus $case) => [
            'value' => $case->value,
            'labelKey' => $case->labelKey(),
        ], $cases);
    }

    /**
     * A query parameter as a string, or an empty string when it is missing or not a string
     * (`?type[]=bug`), so `tryFrom()` answers null for it.
     */
    private function queryString(Request $request, string $key): string
    {
        $value = $request->query($key);

        return is_string($value) ? $value : '';
    }

    /**
     * The name of the route the Tester was on, matched from the page URL they sent. The
     * send posts to the Feedback route in the page's own locale, so that locale's routes
     * are the ones registered. Null when the URL matches no route.
     */
    private function routeNameFor(?string $pageUrl): ?string
    {
        $path = $pageUrl === null ? null : parse_url($pageUrl, PHP_URL_PATH);

        if (! is_string($path)) {
            return null;
        }

        try {
            return Route::getRoutes()->match(Request::create($path))->getName();
        } catch (HttpExceptionInterface) {
            return null;
        }
    }
}
