<?php

use App\Enums\FeedbackStatus;
use App\Enums\FeedbackType;
use App\Http\Controllers\ImpersonationController;
use App\Models\FeedbackComment;
use App\Models\FeedbackItem;
use App\Models\FeedbackScreenshot;
use App\Models\Member;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * Tester feedback: triage and filters (#679, ADR-0029 §7, §13). The Support-operator sets
 * an item's status, deletes an item with its comments and screenshots, and deletes a
 * comment. The check is `isSupportOperator()` on the request's Member, never a Gate
 * (ADR-0017 §5), so a super-tier executive who is not an operator is refused, and during
 * impersonation the Persona's answer applies. Testers filter the Feedback page by type and
 * status through query parameters. Outside production only, in the same two layers as the
 * rest of the feedback routes.
 *
 * Asserted from outside: the HTTP response, the Inertia props, the rows on the feedback
 * connection, and the files on the faked private disk.
 */

beforeEach(function () {
    $this->migrateFeedbackDatabase();
    Storage::fake('local');
});

// --- Status -----------------------------------------------------------------

it('lets the Support-operator change an item status, shown on the list and the item page', function () {
    $item = FeedbackItem::factory()->create();
    $operator = Member::factory()->operator()->create();

    $this->actingAs($operator)
        ->from("/feedback/{$item->id}")
        ->patch("/feedback/{$item->id}/status", ['status' => 'confirmed'])
        ->assertRedirect("/feedback/{$item->id}");

    expect($item->fresh()->status)->toBe(FeedbackStatus::Confirmed);

    $this->get('/feedback')
        ->assertInertia(fn (Assert $page) => $page
            ->where('items.0.status', 'confirmed')
            ->where('items.0.statusLabelKey', 'feedback.status.confirmed'));

    $this->get("/feedback/{$item->id}")
        ->assertInertia(fn (Assert $page) => $page
            ->where('item.status', 'confirmed')
            ->where('item.statusLabelKey', 'feedback.status.confirmed'));
});

it('accepts every status', function (FeedbackStatus $status) {
    $item = FeedbackItem::factory()->create();

    $this->actingAs(Member::factory()->operator()->create())
        ->patch("/feedback/{$item->id}/status", ['status' => $status->value])
        ->assertSessionHasNoErrors();

    expect($item->fresh()->status)->toBe($status);
})->with(FeedbackStatus::cases());

it('rejects a status that does not exist', function (mixed $status) {
    $item = FeedbackItem::factory()->create();

    $this->actingAs(Member::factory()->operator()->create())
        ->patch("/feedback/{$item->id}/status", ['status' => $status])
        ->assertSessionHasErrors('status');

    expect($item->fresh()->status)->toBe(FeedbackStatus::New);
})->with([
    'unknown' => ['closed'],
    'missing' => [null],
]);

it('serves the status route at its French twin', function () {
    $item = FeedbackItem::factory()->create();

    $this->actingAs(Member::factory()->operator()->create());

    $this->withLocaleRoutes('fr', function () use ($item) {
        $this->patch("/fr/retroaction/{$item->id}/statut", ['status' => 'fixed'])
            ->assertSessionHasNoErrors();
    });

    expect($item->fresh()->status)->toBe(FeedbackStatus::Fixed);
});

// --- Delete an item -----------------------------------------------------------

it('lets the Support-operator delete an item with its comments, screenshot rows, and files', function () {
    $item = FeedbackItem::factory()->create();
    $other = FeedbackItem::factory()->create();
    FeedbackComment::factory()->count(2)->for($item)->create();
    $otherComment = FeedbackComment::factory()->for($other)->create();
    $screenshots = FeedbackScreenshot::factory()->count(2)->for($item)->create();
    $otherScreenshot = FeedbackScreenshot::factory()->for($other)->create();

    foreach ([...$screenshots, $otherScreenshot] as $screenshot) {
        Storage::disk('local')->put($screenshot->storage_path, 'png-bytes');
    }

    $this->actingAs(Member::factory()->operator()->create())
        ->delete("/feedback/{$item->id}")
        ->assertRedirect('/feedback');

    expect(FeedbackItem::find($item->id))->toBeNull()
        ->and(FeedbackComment::where('feedback_item_id', $item->id)->count())->toBe(0)
        ->and(FeedbackScreenshot::where('feedback_item_id', $item->id)->count())->toBe(0)
        ->and($otherComment->fresh())->not->toBeNull()
        ->and($otherScreenshot->fresh())->not->toBeNull();

    foreach ($screenshots as $screenshot) {
        Storage::disk('local')->assertMissing($screenshot->storage_path);
    }
    Storage::disk('local')->assertExists($otherScreenshot->storage_path);
});

it('deletes an item whose screenshot file is already gone', function () {
    $item = FeedbackItem::factory()->create();
    FeedbackScreenshot::factory()->for($item)->create();

    $this->actingAs(Member::factory()->operator()->create())
        ->delete("/feedback/{$item->id}")
        ->assertRedirect('/feedback');

    expect(FeedbackItem::find($item->id))->toBeNull()
        ->and(FeedbackScreenshot::count())->toBe(0);
});

it('serves the item delete at its French twin and returns to the French list', function () {
    $item = FeedbackItem::factory()->create();

    $this->actingAs(Member::factory()->operator()->create());

    $this->withLocaleRoutes('fr', function () use ($item) {
        $this->delete("/fr/retroaction/{$item->id}")
            ->assertRedirect('/fr/retroaction');
    });

    expect(FeedbackItem::find($item->id))->toBeNull();
});

// --- Delete a comment ---------------------------------------------------------

it('lets the Support-operator delete one comment', function () {
    $item = FeedbackItem::factory()->create();
    [$gone, $kept] = FeedbackComment::factory()->count(2)->for($item)->create();

    $this->actingAs(Member::factory()->operator()->create())
        ->from("/feedback/{$item->id}")
        ->delete("/feedback/{$item->id}/comments/{$gone->id}")
        ->assertRedirect("/feedback/{$item->id}");

    expect(FeedbackComment::find($gone->id))->toBeNull()
        ->and($kept->fresh())->not->toBeNull()
        ->and($item->fresh())->not->toBeNull();
});

it('returns 404 for a comment under another item', function () {
    $item = FeedbackItem::factory()->create();
    $comment = FeedbackComment::factory()->for(FeedbackItem::factory())->create();

    $this->actingAs(Member::factory()->operator()->create())
        ->delete("/feedback/{$item->id}/comments/{$comment->id}")
        ->assertNotFound();

    expect($comment->fresh())->not->toBeNull();
});

it('serves the comment delete at its French twin', function () {
    $item = FeedbackItem::factory()->create();
    $comment = FeedbackComment::factory()->for($item)->create();

    $this->actingAs(Member::factory()->operator()->create());

    $this->withLocaleRoutes('fr', function () use ($item, $comment) {
        $this->delete("/fr/retroaction/{$item->id}/commentaires/{$comment->id}")
            ->assertRedirect();
    });

    expect(FeedbackComment::find($comment->id))->toBeNull();
});

// --- Who may triage -----------------------------------------------------------

it('shows the triage controls to the Support-operator only', function (Closure $member, bool $canManage) {
    $item = FeedbackItem::factory()->create();
    $comment = FeedbackComment::factory()->for($item)->create();

    $this->actingAs($member())
        ->get("/feedback/{$item->id}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('can.manage', $canManage)
            ->where('statusHref', "/feedback/{$item->id}/status")
            ->where('deleteHref', "/feedback/{$item->id}")
            ->where('comments.0.deleteHref', "/feedback/{$item->id}/comments/{$comment->id}")
            ->where('statuses', collect(FeedbackStatus::cases())->map(fn (FeedbackStatus $status) => [
                'value' => $status->value,
                'labelKey' => $status->labelKey(),
            ])->all()));
})->with([
    'operator' => [fn () => Member::factory()->operator()->create(), true],
    'ordinary Member' => [fn () => Member::factory()->create(), false],
    'super-tier executive' => [fn () => Member::factory()->superTier()->create(), false],
]);

it('refuses status and delete to anyone who is not the Support-operator', function (Closure $member) {
    $item = FeedbackItem::factory()->create();
    $comment = FeedbackComment::factory()->for($item)->create();
    $screenshot = FeedbackScreenshot::factory()->for($item)->create();
    Storage::disk('local')->put($screenshot->storage_path, 'png-bytes');

    $this->actingAs($member());

    $this->patch("/feedback/{$item->id}/status", ['status' => 'fixed'])->assertForbidden();
    $this->delete("/feedback/{$item->id}/comments/{$comment->id}")->assertForbidden();
    $this->delete("/feedback/{$item->id}")->assertForbidden();

    expect($item->fresh()->status)->toBe(FeedbackStatus::New)
        ->and($comment->fresh())->not->toBeNull()
        ->and($screenshot->fresh())->not->toBeNull();
    Storage::disk('local')->assertExists($screenshot->storage_path);
})->with([
    'ordinary Member' => [fn () => Member::factory()->create()],
    'super-tier executive' => [fn () => Member::factory()->superTier()->create()],
]);

it('answers for the Persona while the operator impersonates one', function () {
    $item = FeedbackItem::factory()->create();
    $comment = FeedbackComment::factory()->for($item)->create();
    $operator = Member::factory()->operator()->create();
    $persona = Member::factory()->superTier()->create();

    $this->actingAs($persona)
        ->withSession([ImpersonationController::OPERATOR_KEY => $operator->id]);

    $this->get("/feedback/{$item->id}")
        ->assertInertia(fn (Assert $page) => $page->where('can.manage', false));

    $this->patch("/feedback/{$item->id}/status", ['status' => 'fixed'])->assertForbidden();
    $this->delete("/feedback/{$item->id}/comments/{$comment->id}")->assertForbidden();
    $this->delete("/feedback/{$item->id}")->assertForbidden();

    expect($item->fresh()->status)->toBe(FeedbackStatus::New)
        ->and($comment->fresh())->not->toBeNull();
});

it('refuses a Member who is not the operator before it validates the status', function () {
    // A refused Member learns nothing about the valid statuses: the 403 comes first.
    $item = FeedbackItem::factory()->create();

    $this->actingAs(Member::factory()->create())
        ->patch("/feedback/{$item->id}/status", ['status' => 'closed'])
        ->assertForbidden();
});

it('sends a guest to login from the triage routes', function () {
    $item = FeedbackItem::factory()->create();
    $comment = FeedbackComment::factory()->for($item)->create();

    $this->patch("/feedback/{$item->id}/status", ['status' => 'fixed'])->assertRedirect('/login');
    $this->delete("/feedback/{$item->id}")->assertRedirect('/login');
    $this->delete("/feedback/{$item->id}/comments/{$comment->id}")->assertRedirect('/login');

    expect(FeedbackItem::find($item->id))->not->toBeNull();
});

// --- Filters ------------------------------------------------------------------

it('filters the Feedback page by type, by status, and by both', function (array $query, array $expected) {
    $bugNew = FeedbackItem::factory()->create(['type' => FeedbackType::Bug, 'tester_name' => 'bug-new']);
    $bugFixed = FeedbackItem::factory()->create(['type' => FeedbackType::Bug, 'tester_name' => 'bug-fixed']);
    $bugFixed->forceFill(['status' => FeedbackStatus::Fixed])->save();
    $translationFixed = FeedbackItem::factory()->create(['type' => FeedbackType::Translation, 'tester_name' => 'translation-fixed']);
    $translationFixed->forceFill(['status' => FeedbackStatus::Fixed])->save();

    $this->actingAs(Member::factory()->create())
        ->get('/feedback?'.http_build_query($query))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('items', fn ($items) => collect($items)->pluck('testerName')->sort()->values()->all() === $expected)
            ->where('filters.type', $query['type'] ?? null)
            ->where('filters.status', $query['status'] ?? null));
})->with([
    'no filter' => [[], ['bug-fixed', 'bug-new', 'translation-fixed']],
    'type' => [['type' => 'bug'], ['bug-fixed', 'bug-new']],
    'status' => [['status' => 'fixed'], ['bug-fixed', 'translation-fixed']],
    'both' => [['type' => 'bug', 'status' => 'fixed'], ['bug-fixed']],
    'open' => [['status' => 'open'], ['bug-new']],
    'closed' => [['status' => 'closed'], ['bug-fixed', 'translation-fixed']],
    'type and closed' => [['type' => 'bug', 'status' => 'closed'], ['bug-fixed']],
    'nothing matches' => [['type' => 'confusing'], []],
]);

it('groups New and Confirmed as open, and the rest as closed', function () {
    expect(FeedbackStatus::grouped(true))->toBe([FeedbackStatus::New, FeedbackStatus::Confirmed])
        ->and(FeedbackStatus::grouped(false))->toBe([FeedbackStatus::Fixed, FeedbackStatus::WontFix, FeedbackStatus::Duplicate]);
});

it('ignores a filter value that does not exist', function () {
    FeedbackItem::factory()->count(2)->create();

    $this->actingAs(Member::factory()->create())
        ->get('/feedback?type=nonsense&status=nonsense')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('items', 2)
            ->where('filters.type', null)
            ->where('filters.status', null));
});

it('gives the Feedback page the type and status options for its filters', function () {
    $this->actingAs(Member::factory()->create())
        ->get('/feedback')
        ->assertInertia(fn (Assert $page) => $page
            ->where('types', collect(FeedbackType::cases())->map(fn (FeedbackType $type) => [
                'value' => $type->value,
                'labelKey' => $type->labelKey(),
            ])->all())
            ->where('statuses', collect(FeedbackStatus::cases())->map(fn (FeedbackStatus $status) => [
                'value' => $status->value,
                'labelKey' => $status->labelKey(),
            ])->all())
            ->where('listHref', '/feedback'));
});

// --- Production hard-off (two-layer environment boundary) -------------------

it('does not register the triage routes in production (route layer)', function () {
    $this->app->detectEnvironment(fn () => 'production');

    $router = $this->app['router'];
    $router->setRoutes(new RouteCollection);
    Route::middleware('web')->group(base_path('routes/web.php'));

    expect(Route::has('feedback.status.update'))->toBeFalse()
        ->and(Route::has('feedback.destroy'))->toBeFalse()
        ->and(Route::has('feedback.comments.destroy'))->toBeFalse();
});

it('returns 404 from the triage actions in production (controller layer)', function () {
    $item = FeedbackItem::factory()->create();
    $comment = FeedbackComment::factory()->for($item)->create();
    $this->app->detectEnvironment(fn () => 'production');

    // CSRF skips itself only in the testing environment; drop it so the request
    // reaches the controller and its own production check answers.
    $this->actingAs(Member::factory()->operator()->create())
        ->withoutMiddleware(ValidateCsrfToken::class);

    $this->patch("/feedback/{$item->id}/status", ['status' => 'fixed'])->assertNotFound();
    $this->delete("/feedback/{$item->id}/comments/{$comment->id}")->assertNotFound();
    $this->delete("/feedback/{$item->id}")->assertNotFound();

    expect($item->fresh()->status)->toBe(FeedbackStatus::New)
        ->and($comment->fresh())->not->toBeNull();
});
