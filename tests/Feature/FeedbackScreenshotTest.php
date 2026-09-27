<?php

use App\Models\FeedbackItem;
use App\Models\FeedbackScreenshot;
use App\Models\Member;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * Tester feedback: screenshots (#678, ADR-0029 §9). A Tester adds up to 3 images to a
 * Feedback item, and every Tester sees them on the item page. Each file lands on the
 * private disk under a UUID name, with a row on the feedback connection that keeps the
 * original filename. Each image downloads through a controller with a policy check.
 * Outside production only, in the same two layers as the rest of the feedback routes.
 *
 * Asserted from outside: the HTTP response, the Inertia props, the rows on the feedback
 * connection, and the files on the faked private disk.
 */

beforeEach(function () {
    $this->migrateFeedbackDatabase();
    Storage::fake('local');
});

// A valid send with screenshots.
function screenshotPayload(array $screenshots): array
{
    return [
        'tester_name' => 'Pat Tester',
        'type' => 'bug',
        'message' => 'The Save button does nothing.',
        'page_url' => '/dashboard',
        'screenshots' => $screenshots,
    ];
}

// --- Upload -------------------------------------------------------------------

it('stores up to 3 screenshots on the private disk under UUID names', function () {
    $this->actingAs(Member::factory()->create())
        ->from('/dashboard')
        ->post('/feedback', screenshotPayload([
            UploadedFile::fake()->image('calendar saturday.png', 400, 300),
            UploadedFile::fake()->image('after-click.jpg', 400, 300),
            UploadedFile::fake()->image('menu.gif', 40, 30),
        ]))
        ->assertRedirect('/dashboard')
        ->assertSessionHasNoErrors();

    $item = FeedbackItem::sole();
    $screenshots = $item->screenshots()->orderBy('id')->get();

    expect($screenshots)->toHaveCount(3)
        ->and($screenshots->pluck('original_filename')->all())->toBe(['calendar saturday.png', 'after-click.jpg', 'menu.gif'])
        ->and($screenshots->pluck('mime_type')->all())->toBe(['image/png', 'image/jpeg', 'image/gif'])
        ->and($screenshots[0]->getConnectionName())->toBe('feedback');

    foreach ($screenshots as $screenshot) {
        expect($screenshot->storage_path)->toMatch('#^feedback-screenshots/[0-9a-f-]{36}$#')
            ->and($screenshot->size_bytes)->toBe(Storage::disk('local')->size($screenshot->storage_path));
        Storage::disk('local')->assertExists($screenshot->storage_path);
    }
});

it('still sends an item with no screenshots', function () {
    $this->actingAs(Member::factory()->create())
        ->post('/feedback', screenshotPayload([]))
        ->assertSessionHasNoErrors();

    expect(FeedbackItem::sole()->screenshots()->count())->toBe(0);
});

it('keeps a safe version of the original filename', function () {
    $this->actingAs(Member::factory()->create())
        ->post('/feedback', screenshotPayload([UploadedFile::fake()->image('Écran: capture*2 <draft>?.png')]))
        ->assertSessionHasNoErrors();

    expect(FeedbackScreenshot::sole()->original_filename)->toBe('Écran capture2 draft.png');
});

it('rejects a fourth screenshot', function () {
    $this->actingAs(Member::factory()->create())
        ->post('/feedback', screenshotPayload([
            UploadedFile::fake()->image('1.png'),
            UploadedFile::fake()->image('2.png'),
            UploadedFile::fake()->image('3.png'),
            UploadedFile::fake()->image('4.png'),
        ]))
        ->assertSessionHasErrors(['screenshots' => 'You can add up to 3 screenshots.']);

    expect(FeedbackItem::count())->toBe(0)
        ->and(Storage::disk('local')->allFiles())->toBe([]);
});

it('rejects a file that is not a PNG, JPEG, WebP, or GIF image', function () {
    $this->actingAs(Member::factory()->create())
        ->post('/feedback', screenshotPayload([UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf')]))
        ->assertSessionHasErrors(['screenshots.0' => 'notes.pdf was not added. Use a PNG, JPEG, WebP, or GIF image.']);

    expect(FeedbackItem::count())->toBe(0);
});

it('rejects an image over 5 MB', function () {
    $this->actingAs(Member::factory()->create())
        ->post('/feedback', screenshotPayload([UploadedFile::fake()->image('full-page.png')->size(5 * 1024 + 1)]))
        ->assertSessionHasErrors(['screenshots.0' => 'full-page.png was not added. It is larger than 5 MB.']);

    expect(FeedbackItem::count())->toBe(0);
});

it('rejects an image the server refused as too large before validation', function () {
    // PHP drops a file over upload_max_filesize and flags the upload; no contents reach us.
    $refused = new UploadedFile(UploadedFile::fake()->image('huge.png')->getPathname(), 'huge.png', 'image/png', UPLOAD_ERR_INI_SIZE, true);

    $this->actingAs(Member::factory()->create())
        ->post('/feedback', screenshotPayload([$refused]))
        ->assertSessionHasErrors(['screenshots.0' => 'huge.png was not added. It is larger than 5 MB.']);

    expect(FeedbackItem::count())->toBe(0);
});

it('rejects a screenshot that is not a file', function () {
    $this->actingAs(Member::factory()->create())
        ->post('/feedback', screenshotPayload(['not-a-file']))
        ->assertSessionHasErrors('screenshots.0');

    expect(FeedbackItem::count())->toBe(0);
});

it('rejects the files in French for a French page', function () {
    $this->withLocaleRoutes('fr', function () {
        $this->actingAs(Member::factory()->create())
            ->post('/fr/retroaction', screenshotPayload([UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf')]))
            ->assertSessionHasErrors(['screenshots.0' => 'notes.pdf n’a pas été ajouté. Utilisez une image PNG, JPEG, WebP ou GIF.']);
    });
});

// --- Item page ----------------------------------------------------------------

it('shows the screenshots on the item page', function () {
    $item = FeedbackItem::factory()->create();
    $first = FeedbackScreenshot::factory()->for($item)->create(['original_filename' => 'calendar.png', 'size_bytes' => 421888]);
    $second = FeedbackScreenshot::factory()->for($item)->create(['original_filename' => 'after.png']);
    FeedbackScreenshot::factory()->create();

    $this->actingAs(Member::factory()->create())
        ->get("/feedback/{$item->id}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('screenshots', 2)
            ->where('screenshots.0.id', $first->id)
            ->where('screenshots.0.filename', 'calendar.png')
            ->where('screenshots.0.sizeBytes', 421888)
            ->where('screenshots.0.href', "/feedback/screenshots/{$first->id}")
            ->where('screenshots.1.id', $second->id));
});

// --- Download -----------------------------------------------------------------

it('lets any logged-in Member download a screenshot with its original name', function () {
    $screenshot = FeedbackScreenshot::factory()->create([
        'original_filename' => 'calendar saturday.png',
        'mime_type' => 'image/png',
    ]);
    Storage::disk('local')->put($screenshot->storage_path, 'png-bytes');

    $response = $this->actingAs(Member::factory()->create())
        ->get("/feedback/screenshots/{$screenshot->id}")
        ->assertOk()
        ->assertDownload('calendar saturday.png')
        ->assertHeader('Content-Type', 'image/png');

    expect($response->streamedContent())->toBe('png-bytes');
});

it('serves the French item page and download at /fr/retroaction', function () {
    $screenshot = FeedbackScreenshot::factory()->create(['original_filename' => 'capture.png']);
    Storage::disk('local')->put($screenshot->storage_path, 'png-bytes');
    $this->actingAs(Member::factory()->create());

    $this->withLocaleRoutes('fr', function () use ($screenshot) {
        $this->get("/fr/retroaction/{$screenshot->feedback_item_id}")
            ->assertInertia(fn (Assert $page) => $page
                ->where('screenshots.0.href', "/fr/retroaction/captures/{$screenshot->id}"));

        $this->get("/fr/retroaction/captures/{$screenshot->id}")
            ->assertOk()
            ->assertDownload('capture.png');
    });
});

it('returns 404 for a screenshot whose file is gone', function () {
    $screenshot = FeedbackScreenshot::factory()->create();

    $this->actingAs(Member::factory()->create())
        ->get("/feedback/screenshots/{$screenshot->id}")
        ->assertNotFound();
});

it('sends a guest to login from the download route', function () {
    $screenshot = FeedbackScreenshot::factory()->create();

    $this->get("/feedback/screenshots/{$screenshot->id}")->assertRedirect(route('login'));
});

// --- Production hard-off (two-layer environment boundary) -------------------

it('does not register the download route in production (route layer)', function () {
    $this->app->detectEnvironment(fn () => 'production');

    $router = $this->app['router'];
    $router->setRoutes(new RouteCollection);
    Route::middleware('web')->group(base_path('routes/web.php'));

    expect(Route::has('feedback.screenshots.download'))->toBeFalse();
});

it('returns 404 from the download action in production (controller layer)', function () {
    $screenshot = FeedbackScreenshot::factory()->create();
    Storage::disk('local')->put($screenshot->storage_path, 'png-bytes');
    $this->app->detectEnvironment(fn () => 'production');

    $this->actingAs(Member::factory()->create())
        ->get("/feedback/screenshots/{$screenshot->id}")
        ->assertNotFound();
});
