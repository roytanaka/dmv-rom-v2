<?php

use App\Enums\FeedbackStatus;
use App\Enums\FeedbackType;
use App\Http\Controllers\ImpersonationController;
use App\Models\FeedbackComment;
use App\Models\FeedbackItem;
use App\Models\Member;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * Tester feedback: send and list (#676, ADR-0029). A Tester sends a Feedback item from the
 * help menu and sees it on the Feedback page. Items live on the `feedback` connection, a
 * second database that deploys never reset. Outside production only, in two layers: the
 * routes are not registered in production, and the controller returns 404 there.
 *
 * Asserted from outside: the HTTP response, the Inertia props, and the rows on the
 * feedback connection.
 */

beforeEach(function () {
    $this->migrateFeedbackDatabase();
});

// A valid send from the Dashboard: what the Tester types plus the client context.
function feedbackPayload(array $overrides = []): array
{
    return array_merge([
        'tester_name' => 'Pat Tester',
        'type' => 'bug',
        'message' => 'The Save button does nothing.',
        'page_url' => '/dashboard',
        'user_agent' => 'Mozilla/5.0 (Macintosh)',
        'viewport_width' => 1280,
        'viewport_height' => 800,
    ], $overrides);
}

// --- Sending --------------------------------------------------------------

it('stores a Feedback item on the feedback connection with the server-captured context', function () {
    config(['app.version' => ['commit' => 'abc1234def5678', 'deployed_at' => '2026-09-26T23:14:24Z']]);
    $member = Member::factory()->create(['first_name' => 'Margaret', 'last_name' => 'Chen', 'email' => 'margaret@dmv.test']);

    $this->actingAs($member)
        ->from('/dashboard')
        ->post('/feedback', feedbackPayload())
        ->assertRedirect('/dashboard')
        ->assertSessionHasNoErrors();

    $item = FeedbackItem::sole();

    expect($item->getConnectionName())->toBe('feedback')
        ->and($item->tester_name)->toBe('Pat Tester')
        ->and($item->type)->toBe(FeedbackType::Bug)
        ->and($item->status)->toBe(FeedbackStatus::New)
        ->and($item->message)->toBe('The Save button does nothing.')
        ->and($item->page_url)->toBe('/dashboard')
        ->and($item->user_agent)->toBe('Mozilla/5.0 (Macintosh)')
        ->and($item->viewport_width)->toBe(1280)
        ->and($item->viewport_height)->toBe(800)
        ->and($item->route_name)->toBe('dashboard')
        ->and($item->locale)->toBe('en')
        ->and($item->member_name)->toBe('Margaret Chen')
        ->and($item->member_email)->toBe('margaret@dmv.test')
        ->and($item->impersonator_name)->toBeNull()
        ->and($item->app_version)->toBe('abc1234 2026-09-26T23:14:24Z')
        ->and($item->created_at)->not->toBeNull();

    // Nothing lands in the main database.
    expect(DB::connection()->getSchemaBuilder()->hasTable('feedback_items'))->toBeFalse();
});

it('records the impersonator name while impersonating', function () {
    $operator = Member::factory()->create(['first_name' => 'Sam', 'last_name' => 'Operator']);
    $persona = Member::factory()->create(['first_name' => 'Chris', 'last_name' => 'Chair']);

    $this->actingAs($persona)
        ->withSession([ImpersonationController::OPERATOR_KEY => $operator->id])
        ->post('/feedback', feedbackPayload())
        ->assertSessionHasNoErrors();

    expect(FeedbackItem::sole())
        ->member_name->toBe('Chris Chair')
        ->impersonator_name->toBe('Sam Operator');
});

it('stores a null app version when none is configured', function () {
    config(['app.version' => null]);

    $this->actingAs(Member::factory()->create())
        ->post('/feedback', feedbackPayload())
        ->assertSessionHasNoErrors();

    expect(FeedbackItem::sole()->app_version)->toBeNull();
});

it('starts every item as New, whatever the request says', function () {
    $this->actingAs(Member::factory()->create())
        ->post('/feedback', feedbackPayload(['status' => 'fixed']))
        ->assertSessionHasNoErrors();

    expect(FeedbackItem::sole()->status)->toBe(FeedbackStatus::New);
});

it('stores a null route name when the page URL matches no route', function () {
    $this->actingAs(Member::factory()->create())
        ->post('/feedback', feedbackPayload(['page_url' => '/no/such/page?x=1']))
        ->assertSessionHasNoErrors();

    expect(FeedbackItem::sole())
        ->page_url->toBe('/no/such/page?x=1')
        ->route_name->toBeNull();
});

it('records the French locale and route when sent from a French page', function () {
    $this->actingAs(Member::factory()->create());

    $this->withLocaleRoutes('fr', function () {
        $this->post('/fr/retroaction', feedbackPayload(['page_url' => '/fr/tableau-de-bord']))
            ->assertSessionHasNoErrors();
    });

    expect(FeedbackItem::sole())
        ->locale->toBe('fr')
        ->route_name->toBe('dashboard');
});

it('accepts every Feedback type', function (FeedbackType $type) {
    $this->actingAs(Member::factory()->create())
        ->post('/feedback', feedbackPayload(['type' => $type->value]))
        ->assertSessionHasNoErrors();

    expect(FeedbackItem::sole()->type)->toBe($type);
})->with(FeedbackType::cases());

it('accepts a send with no client context', function () {
    $this->actingAs(Member::factory()->create())
        ->post('/feedback', ['tester_name' => 'Pat', 'type' => 'other', 'message' => 'Hello'])
        ->assertSessionHasNoErrors();

    expect(FeedbackItem::sole())
        ->page_url->toBeNull()
        ->user_agent->toBeNull()
        ->viewport_width->toBeNull();
});

// --- Validation -------------------------------------------------------------

it('rejects an invalid send', function (array $overrides, string $field) {
    $this->actingAs(Member::factory()->create())
        ->post('/feedback', feedbackPayload($overrides))
        ->assertSessionHasErrors($field);

    expect(FeedbackItem::count())->toBe(0);
})->with([
    'missing name' => [['tester_name' => ''], 'tester_name'],
    'name too long' => [['tester_name' => str_repeat('a', 101)], 'tester_name'],
    'bad type' => [['type' => 'praise'], 'type'],
    'missing type' => [['type' => ''], 'type'],
    'missing message' => [['message' => ''], 'message'],
    'message too long' => [['message' => str_repeat('a', 5001)], 'message'],
    'page URL too long' => [['page_url' => '/'.str_repeat('a', 2048)], 'page_url'],
    'user agent too long' => [['user_agent' => str_repeat('a', 513)], 'user_agent'],
    'viewport not a number' => [['viewport_width' => 'wide'], 'viewport_width'],
]);

// --- Feedback page -----------------------------------------------------------

it('lists every Feedback item, newest first', function () {
    $older = FeedbackItem::factory()->create(['tester_name' => 'First', 'created_at' => now()->subDay()]);
    $newer = FeedbackItem::factory()->create([
        'tester_name' => 'Second',
        'type' => FeedbackType::Translation,
        'message' => str_repeat('word ', 100),
        'created_at' => now(),
    ]);

    $this->actingAs(Member::factory()->create())
        ->get('/feedback')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('feedback/Index')
            ->has('items', 2)
            ->where('items.0.id', $newer->id)
            ->where('items.0.typeLabelKey', 'feedback.type.translation')
            ->where('items.0.status', 'new')
            ->where('items.0.statusLabelKey', 'feedback.status.new')
            ->where('items.0.testerName', 'Second')
            ->where('items.0.excerpt', Str::limit($newer->message, 160))
            ->where('items.0.createdAt', $newer->created_at->toIso8601String())
            ->where('items.1.id', $older->id));
});

it('counts each Feedback item\'s comments on the Feedback page', function () {
    $commented = FeedbackItem::factory()->create(['created_at' => now()]);
    $quiet = FeedbackItem::factory()->create(['created_at' => now()->subDay()]);
    FeedbackComment::factory()->count(3)->for($commented)->create();

    $this->actingAs(Member::factory()->create())
        ->get('/feedback')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('items.0.id', $commented->id)
            ->where('items.0.commentsCount', 3)
            ->where('items.1.id', $quiet->id)
            ->where('items.1.commentsCount', 0));
});

it('shows a sent item at the top of the Feedback page', function () {
    FeedbackItem::factory()->create(['created_at' => now()->subHour()]);
    $this->actingAs(Member::factory()->create());

    $this->post('/feedback', feedbackPayload(['tester_name' => 'Just Sent']))->assertSessionHasNoErrors();

    $this->get('/feedback')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('items', 2)
            ->where('items.0.testerName', 'Just Sent')
            ->where('items.0.statusLabelKey', 'feedback.status.new'));
});

it('sends a guest to login from the Feedback page and the store route', function () {
    $this->get('/feedback')->assertRedirect(route('login'));
    $this->post('/feedback', feedbackPayload())->assertRedirect(route('login'));

    expect(FeedbackItem::count())->toBe(0);
});

it('serves the Feedback page at its French twin', function () {
    FeedbackItem::factory()->create();
    $this->actingAs(Member::factory()->create());

    $this->withLocaleRoutes('fr', function () {
        $this->get('/fr/retroaction')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('feedback/Index')
                ->where('locale', 'fr')
                ->has('items', 1));
    });
});

// --- Persistence ------------------------------------------------------------

it('keeps Feedback items through a migrate:fresh of the default connection', function () {
    FeedbackItem::factory()->create(['tester_name' => 'Survivor']);

    // Leave the RefreshDatabase transaction, rebuild the main schema the way a staging
    // deploy does, then re-open the transaction for the rest of the test.
    $connection = DB::connection();
    $connection->rollBack();
    $this->artisan('migrate:fresh');
    $this->app[Kernel::class]->setArtisan(null);
    $this->updateLocalCacheOfInMemoryDatabases();
    $connection->beginTransaction();

    expect(FeedbackItem::sole()->tester_name)->toBe('Survivor');
});

// --- Production hard-off (two-layer environment boundary) -------------------

it('does not register the feedback routes in production (route layer)', function () {
    $this->app->detectEnvironment(fn () => 'production');

    $router = $this->app['router'];
    $router->setRoutes(new RouteCollection);
    Route::middleware('web')->group(base_path('routes/web.php'));

    expect(Route::has('feedback'))->toBeFalse()
        ->and(Route::has('feedback.store'))->toBeFalse();
});

it('returns 404 from the feedback controller in production (controller layer)', function () {
    $this->app->detectEnvironment(fn () => 'production');
    $this->actingAs(Member::factory()->create());

    $this->get('/feedback')->assertNotFound();

    // CSRF skips itself only in the testing environment; drop it so the request
    // reaches the controller and its own production check answers.
    $this->withoutMiddleware(ValidateCsrfToken::class)
        ->post('/feedback', feedbackPayload())
        ->assertNotFound();

    expect(FeedbackItem::count())->toBe(0);
});
