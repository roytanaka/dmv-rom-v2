<?php

use App\Enums\FeedbackType;
use App\Models\FeedbackComment;
use App\Models\FeedbackItem;
use App\Models\Member;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * Tester feedback: the item page and comments (#677, ADR-0029 §8, §13). A Tester opens a
 * Feedback item on its own page, reads its message, context, and comments, and adds a
 * comment. Any logged-in Member may comment. Comments are a flat list, oldest first,
 * with no editing. Outside production only, in the same two layers as the rest of the
 * feedback routes.
 *
 * Asserted from outside: the HTTP response, the Inertia props, and the rows on the
 * feedback connection.
 */

beforeEach(function () {
    $this->migrateFeedbackDatabase();
});

// --- Item page --------------------------------------------------------------

it('links each row on the Feedback page to its item page', function () {
    $item = FeedbackItem::factory()->create();

    $this->actingAs(Member::factory()->create())
        ->get('/feedback')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('items.0.href', "/feedback/{$item->id}"));
});

it('shows an item with its message and captured context', function () {
    $item = FeedbackItem::factory()->create([
        'tester_name' => 'Pat Tester',
        'type' => FeedbackType::Translation,
        'message' => "Line one.\nLine two.",
        'page_url' => '/members/12?tab=roles',
        'route_name' => 'members.show',
        'locale' => 'fr',
        'user_agent' => 'Mozilla/5.0 (Macintosh)',
        'viewport_width' => 390,
        'viewport_height' => 844,
        'member_name' => 'Chris Chair',
        'member_email' => 'chris@dmv.test',
        'impersonator_name' => 'Sam Operator',
        'app_version' => 'abc1234 2026-09-26T23:14:24Z',
    ]);

    $this->actingAs(Member::factory()->create())
        ->get("/feedback/{$item->id}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('feedback/Show')
            ->where('item.id', $item->id)
            ->where('item.typeLabelKey', 'feedback.type.translation')
            ->where('item.status', 'new')
            ->where('item.statusLabelKey', 'feedback.status.new')
            ->where('item.testerName', 'Pat Tester')
            ->where('item.message', "Line one.\nLine two.")
            ->where('item.createdAt', $item->created_at->toIso8601String())
            ->where('item.pageUrl', '/members/12?tab=roles')
            ->where('item.pageHref', '/members/12?tab=roles')
            ->where('item.routeName', 'members.show')
            ->where('item.locale', 'fr')
            ->where('item.userAgent', 'Mozilla/5.0 (Macintosh)')
            ->where('item.viewportWidth', 390)
            ->where('item.viewportHeight', 844)
            ->where('item.memberName', 'Chris Chair')
            ->where('item.memberEmail', 'chris@dmv.test')
            ->where('item.impersonatorName', 'Sam Operator')
            ->where('item.appVersion', 'abc1234 2026-09-26T23:14:24Z')
            ->where('listHref', '/feedback')
            ->where('commentHref', "/feedback/{$item->id}/comments")
            ->has('comments', 0));
});

it('links the page URL only when it is a path inside the app', function (?string $pageUrl, ?string $pageHref) {
    $item = FeedbackItem::factory()->create(['page_url' => $pageUrl]);

    $this->actingAs(Member::factory()->create())
        ->get("/feedback/{$item->id}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('item.pageUrl', $pageUrl)
            ->where('item.pageHref', $pageHref));
})->with([
    'app path' => ['/dashboard', '/dashboard'],
    'no URL' => [null, null],
    'script URL' => ['javascript:alert(1)', null],
    'other site' => ['https://example.com/x', null],
    'protocol-relative' => ['//example.com/x', null],
    'backslash trick' => ['/\\example.com/x', null],
    'tab trick' => ["/\t/example.com/x", null],
]);

it('lists the comments oldest first', function () {
    $item = FeedbackItem::factory()->create();
    $later = FeedbackComment::factory()->for($item)->create(['tester_name' => 'Second', 'created_at' => now()]);
    $earlier = FeedbackComment::factory()->for($item)->create([
        'tester_name' => 'First',
        'body' => 'I see it too.',
        'created_at' => now()->subHour(),
    ]);
    // Another item's comment stays on that item.
    FeedbackComment::factory()->for(FeedbackItem::factory())->create();

    $this->actingAs(Member::factory()->create())
        ->get("/feedback/{$item->id}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('comments', 2)
            ->where('comments.0.id', $earlier->id)
            ->where('comments.0.testerName', 'First')
            ->where('comments.0.body', 'I see it too.')
            ->where('comments.0.createdAt', $earlier->created_at->toIso8601String())
            ->where('comments.1.id', $later->id));
});

it('returns 404 for a missing item', function () {
    $this->actingAs(Member::factory()->create());

    $this->get('/feedback/999')->assertNotFound();
    $this->post('/feedback/999/comments', ['tester_name' => 'Pat', 'body' => 'Hello'])->assertNotFound();

    expect(FeedbackComment::count())->toBe(0);
});

it('serves the item page at its French twin', function () {
    $item = FeedbackItem::factory()->create();
    $this->actingAs(Member::factory()->create());

    $this->withLocaleRoutes('fr', function () use ($item) {
        $this->get("/fr/retroaction/{$item->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('feedback/Show')
                ->where('locale', 'fr')
                ->where('listHref', '/fr/retroaction')
                ->where('commentHref', "/fr/retroaction/{$item->id}/commentaires"));
    });
});

// --- Comments -----------------------------------------------------------------

it('lets any logged-in Member add a comment, which appears at the bottom', function () {
    $item = FeedbackItem::factory()->create();
    FeedbackComment::factory()->for($item)->create(['created_at' => now()->subHour()]);

    $this->actingAs(Member::factory()->create())
        ->from("/feedback/{$item->id}")
        ->post("/feedback/{$item->id}/comments", ['tester_name' => 'Pat Tester', 'body' => 'Still broken on my phone.'])
        ->assertRedirect("/feedback/{$item->id}")
        ->assertSessionHasNoErrors();

    $comment = $item->comments()->latest('id')->first();

    expect($comment->getConnectionName())->toBe('feedback')
        ->and($comment->tester_name)->toBe('Pat Tester')
        ->and($comment->body)->toBe('Still broken on my phone.');

    $this->get("/feedback/{$item->id}")
        ->assertInertia(fn (Assert $page) => $page
            ->has('comments', 2)
            ->where('comments.1.testerName', 'Pat Tester')
            ->where('comments.1.body', 'Still broken on my phone.'));
});

it('rejects an invalid comment', function (array $payload, string $field) {
    $item = FeedbackItem::factory()->create();

    $this->actingAs(Member::factory()->create())
        ->post("/feedback/{$item->id}/comments", $payload)
        ->assertSessionHasErrors($field);

    expect(FeedbackComment::count())->toBe(0);
})->with([
    'missing name' => [['tester_name' => '', 'body' => 'Hello'], 'tester_name'],
    'name too long' => [['tester_name' => str_repeat('a', 101), 'body' => 'Hello'], 'tester_name'],
    'missing body' => [['tester_name' => 'Pat', 'body' => ''], 'body'],
    'body too long' => [['tester_name' => 'Pat', 'body' => str_repeat('a', 5001)], 'body'],
]);

it('sends a guest to login from the item page and the comment route', function () {
    $item = FeedbackItem::factory()->create();

    $this->get("/feedback/{$item->id}")->assertRedirect(route('login'));
    $this->post("/feedback/{$item->id}/comments", ['tester_name' => 'Pat', 'body' => 'Hello'])
        ->assertRedirect(route('login'));

    expect(FeedbackComment::count())->toBe(0);
});

// --- Production hard-off (two-layer environment boundary) -------------------

it('does not register the item and comment routes in production (route layer)', function () {
    $this->app->detectEnvironment(fn () => 'production');

    $router = $this->app['router'];
    $router->setRoutes(new RouteCollection);
    Route::middleware('web')->group(base_path('routes/web.php'));

    expect(Route::has('feedback.show'))->toBeFalse()
        ->and(Route::has('feedback.comments.store'))->toBeFalse();
});

it('returns 404 from the item and comment actions in production (controller layer)', function () {
    $item = FeedbackItem::factory()->create();
    $this->app->detectEnvironment(fn () => 'production');
    $this->actingAs(Member::factory()->create());

    $this->get("/feedback/{$item->id}")->assertNotFound();

    // CSRF skips itself only in the testing environment; drop it so the request
    // reaches the controller and its own production check answers.
    $this->withoutMiddleware(ValidateCsrfToken::class)
        ->post("/feedback/{$item->id}/comments", ['tester_name' => 'Pat', 'body' => 'Hello'])
        ->assertNotFound();

    expect(FeedbackComment::count())->toBe(0);
});
