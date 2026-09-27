<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreFeedbackItemRequest;
use App\Models\FeedbackItem;
use App\Models\Member;
use App\Support\AppVersion;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/**
 * Tester feedback (#676, ADR-0029): send a Feedback item, and list them all on the
 * Feedback page. Any logged-in Member may do both (§4).
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
     * The Feedback page: every item, newest first (§13). No policy check beyond `auth`:
     * every logged-in Tester reads every item (ADR-0029 §4).
     */
    public function index(): Response
    {
        $items = FeedbackItem::query()
            ->latest()
            ->orderByDesc('id')
            ->get()
            ->map(fn (FeedbackItem $item) => [
                'id' => $item->id,
                'typeLabelKey' => $item->type->labelKey(),
                'status' => $item->status->value,
                'statusLabelKey' => $item->status->labelKey(),
                'testerName' => $item->tester_name,
                'excerpt' => Str::limit($item->message, 160),
                'createdAt' => $item->created_at->toIso8601String(),
            ]);

        return Inertia::render('feedback/Index', ['items' => $items]);
    }

    /**
     * Store a Feedback item. The request carries what the Tester typed and the client
     * context; the server fills everything it can know itself (§10): the page's route,
     * the locale, the Member, the impersonator, the app version, and the time. Every
     * item starts as New.
     */
    public function store(StoreFeedbackItemRequest $request): RedirectResponse
    {
        $member = $request->user();
        $operatorId = $request->session()->get(ImpersonationController::OPERATOR_KEY);

        FeedbackItem::create([
            ...$request->validated(),
            'route_name' => $this->routeNameFor($request->validated('page_url')),
            'locale' => app()->getLocale(),
            'member_name' => $member->fullName(),
            'member_email' => $member->email,
            'impersonator_name' => $operatorId === null ? null : Member::find($operatorId)?->fullName(),
            'app_version' => AppVersion::label(),
        ]);

        return back();
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
