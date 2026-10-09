<?php

use App\Models\FeedbackComment;
use App\Models\FeedbackCommentImage;
use App\Models\FeedbackItem;
use App\Models\Member;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * Tester feedback: images on comments (#778, spec #773, ADR-0029 §8, §9). A comment carries
 * up to 3 images under the same rules as an item's screenshots. A comment needs text, an
 * image, or both. Each file lands on the private disk under a UUID name and loads through
 * a policy-checked controller, inline for the viewer and as an attachment for Download.
 * Deleting a comment deletes its files.
 *
 * Asserted from outside: the HTTP response, the Inertia props, the rows on the feedback
 * connection, and the files on the faked private disk.
 */

beforeEach(function () {
    $this->migrateFeedbackDatabase();
    Storage::fake('local');
});

// --- Upload -------------------------------------------------------------------

it('stores up to 3 images on a comment, on the private disk under UUID names', function () {
    $item = FeedbackItem::factory()->create();

    $this->actingAs(Member::factory()->create())
        ->from("/feedback/{$item->id}")
        ->post("/feedback/{$item->id}/comments", [
            'tester_name' => 'Pat Tester',
            'body' => 'Here it is on my phone.',
            'images' => [
                UploadedFile::fake()->image('phone.png', 400, 300),
                UploadedFile::fake()->image('tablet.jpg', 400, 300),
                UploadedFile::fake()->image('menu.gif', 40, 30),
            ],
        ])
        ->assertRedirect("/feedback/{$item->id}")
        ->assertSessionHasNoErrors();

    $comment = FeedbackComment::sole();
    $images = $comment->images()->orderBy('id')->get();

    expect($comment->body)->toBe('Here it is on my phone.')
        ->and($images)->toHaveCount(3)
        ->and($images->pluck('original_filename')->all())->toBe(['phone.png', 'tablet.jpg', 'menu.gif'])
        ->and($images->pluck('mime_type')->all())->toBe(['image/png', 'image/jpeg', 'image/gif'])
        ->and($images[0]->getConnectionName())->toBe('feedback');

    foreach ($images as $image) {
        expect($image->storage_path)->toMatch('#^feedback-screenshots/[0-9a-f-]{36}$#')
            ->and($image->size_bytes)->toBe(Storage::disk('local')->size($image->storage_path));
        Storage::disk('local')->assertExists($image->storage_path);
    }
});

it('saves a comment with only images and no text', function () {
    $item = FeedbackItem::factory()->create();

    $this->actingAs(Member::factory()->create())
        ->post("/feedback/{$item->id}/comments", [
            'tester_name' => 'Pat Tester',
            'body' => '',
            'images' => [UploadedFile::fake()->image('phone.png')],
        ])
        ->assertSessionHasNoErrors();

    $comment = FeedbackComment::sole();

    expect($comment->body)->toBeNull()
        ->and($comment->images()->count())->toBe(1);
});

it('rejects a comment with neither text nor an image', function () {
    $item = FeedbackItem::factory()->create();

    $this->actingAs(Member::factory()->create())
        ->post("/feedback/{$item->id}/comments", ['tester_name' => 'Pat Tester', 'body' => ''])
        ->assertSessionHasErrors(['body' => 'Add a comment, an image, or both.']);

    expect(FeedbackComment::count())->toBe(0);
});

it('rejects a fourth image', function () {
    $item = FeedbackItem::factory()->create();

    $this->actingAs(Member::factory()->create())
        ->post("/feedback/{$item->id}/comments", [
            'tester_name' => 'Pat Tester',
            'body' => 'Four views.',
            'images' => [
                UploadedFile::fake()->image('1.png'),
                UploadedFile::fake()->image('2.png'),
                UploadedFile::fake()->image('3.png'),
                UploadedFile::fake()->image('4.png'),
            ],
        ])
        ->assertSessionHasErrors(['images' => 'You can add up to 3 screenshots.']);

    expect(FeedbackComment::count())->toBe(0)
        ->and(Storage::disk('local')->allFiles())->toBe([]);
});

it('rejects an image over 5 MB', function () {
    $item = FeedbackItem::factory()->create();

    $this->actingAs(Member::factory()->create())
        ->post("/feedback/{$item->id}/comments", [
            'tester_name' => 'Pat Tester',
            'images' => [UploadedFile::fake()->image('full-page.png')->size(5 * 1024 + 1)],
        ])
        ->assertSessionHasErrors(['images.0' => 'full-page.png was not added. It is larger than 5 MB.']);

    expect(FeedbackComment::count())->toBe(0);
});

it('rejects a file that is not a PNG, JPEG, WebP, or GIF image', function () {
    $item = FeedbackItem::factory()->create();

    $this->actingAs(Member::factory()->create())
        ->post("/feedback/{$item->id}/comments", [
            'tester_name' => 'Pat Tester',
            'images' => [UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf')],
        ])
        ->assertSessionHasErrors(['images.0' => 'notes.pdf was not added. Use a PNG, JPEG, WebP, or GIF image.']);

    expect(FeedbackComment::count())->toBe(0);
});

it('rejects a comment in French for a French page', function () {
    $item = FeedbackItem::factory()->create();
    $this->actingAs(Member::factory()->create());

    $this->withLocaleRoutes('fr', function () use ($item) {
        $this->post("/fr/retroaction/{$item->id}/commentaires", ['tester_name' => 'Pat', 'body' => ''])
            ->assertSessionHasErrors(['body' => 'Ajoutez un commentaire, une image ou les deux.']);

        $this->post("/fr/retroaction/{$item->id}/commentaires", [
            'tester_name' => 'Pat',
            'images' => [
                UploadedFile::fake()->image('1.png'),
                UploadedFile::fake()->image('2.png'),
                UploadedFile::fake()->image('3.png'),
                UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf'),
            ],
        ])->assertSessionHasErrors([
            'images' => 'Vous pouvez ajouter jusqu’à 3 captures d’écran.',
            'images.3' => 'notes.pdf n’a pas été ajouté. Utilisez une image PNG, JPEG, WebP ou GIF.',
        ]);

        $this->post("/fr/retroaction/{$item->id}/commentaires", [
            'tester_name' => 'Pat',
            'images' => [UploadedFile::fake()->image('grande.png')->size(5 * 1024 + 1)],
        ])->assertSessionHasErrors(['images.0' => 'grande.png n’a pas été ajouté. Il dépasse 5 Mo.']);
    });

    expect(FeedbackComment::count())->toBe(0);
});

// --- Item page ----------------------------------------------------------------

it('shows each comment with its images on the item page', function () {
    $item = FeedbackItem::factory()->create();
    $comment = FeedbackComment::factory()->for($item)->create(['body' => null]);
    $first = FeedbackCommentImage::factory()->for($comment)->create(['original_filename' => 'phone.png', 'size_bytes' => 421888]);
    $second = FeedbackCommentImage::factory()->for($comment)->create(['original_filename' => 'tablet.png']);
    FeedbackComment::factory()->for($item)->create();
    FeedbackCommentImage::factory()->create();

    $this->actingAs(Member::factory()->create())
        ->get("/feedback/{$item->id}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('comments', 2)
            ->where('comments.0.body', null)
            ->has('comments.0.images', 2)
            ->where('comments.0.images.0.id', $first->id)
            ->where('comments.0.images.0.filename', 'phone.png')
            ->where('comments.0.images.0.sizeBytes', 421888)
            ->where('comments.0.images.0.mimeType', 'image/png')
            ->where('comments.0.images.0.href', "/feedback/comment-images/{$first->id}")
            ->where('comments.0.images.0.downloadHref', "/feedback/comment-images/{$first->id}?download=1")
            ->where('comments.0.images.1.id', $second->id)
            ->has('comments.1.images', 0));
});

// --- Delete -------------------------------------------------------------------

it('deletes a comment’s image rows and files with the comment', function () {
    $item = FeedbackItem::factory()->create();
    $comment = FeedbackComment::factory()->for($item)->create();
    $images = FeedbackCommentImage::factory()->count(2)->for($comment)->create();
    $kept = FeedbackCommentImage::factory()->for(FeedbackComment::factory()->for($item))->create();

    foreach ([...$images, $kept] as $image) {
        Storage::disk('local')->put($image->storage_path, 'png-bytes');
    }

    $this->actingAs(Member::factory()->operator()->create())
        ->delete("/feedback/{$item->id}/comments/{$comment->id}")
        ->assertRedirect();

    expect(FeedbackComment::find($comment->id))->toBeNull()
        ->and(FeedbackCommentImage::where('feedback_comment_id', $comment->id)->count())->toBe(0)
        ->and($kept->fresh())->not->toBeNull();

    foreach ($images as $image) {
        Storage::disk('local')->assertMissing($image->storage_path);
    }
    Storage::disk('local')->assertExists($kept->storage_path);
});

it('deletes every comment image row and file with the item', function () {
    $item = FeedbackItem::factory()->create();
    $image = FeedbackCommentImage::factory()->for(FeedbackComment::factory()->for($item))->create();
    $other = FeedbackCommentImage::factory()->create();

    foreach ([$image, $other] as $each) {
        Storage::disk('local')->put($each->storage_path, 'png-bytes');
    }

    $this->actingAs(Member::factory()->operator()->create())
        ->delete("/feedback/{$item->id}")
        ->assertRedirect('/feedback');

    expect(FeedbackCommentImage::find($image->id))->toBeNull()
        ->and($other->fresh())->not->toBeNull();
    Storage::disk('local')->assertMissing($image->storage_path);
    Storage::disk('local')->assertExists($other->storage_path);
});

it('keeps a comment and its image files when a non-operator tries to delete it', function () {
    $image = FeedbackCommentImage::factory()->create();
    Storage::disk('local')->put($image->storage_path, 'png-bytes');
    $comment = $image->feedbackComment;

    $this->actingAs(Member::factory()->create())
        ->delete("/feedback/{$comment->feedback_item_id}/comments/{$comment->id}")
        ->assertForbidden();

    expect($image->fresh())->not->toBeNull();
    Storage::disk('local')->assertExists($image->storage_path);
});

// --- Download -----------------------------------------------------------------

it('serves a comment image inline for the viewer', function () {
    $image = FeedbackCommentImage::factory()->create(['original_filename' => 'phone shot.png', 'mime_type' => 'image/png']);
    Storage::disk('local')->put($image->storage_path, 'png-bytes');

    $response = $this->actingAs(Member::factory()->create())
        ->get("/feedback/comment-images/{$image->id}")
        ->assertOk()
        ->assertHeader('Content-Type', 'image/png')
        ->assertHeader('X-Content-Type-Options', 'nosniff');

    expect($response->headers->get('Content-Disposition'))->toStartWith('inline;')
        ->toContain('phone shot.png')
        ->and($response->streamedContent())->toBe('png-bytes');
});

it('downloads a comment image as an attachment with its original name', function () {
    $image = FeedbackCommentImage::factory()->create(['original_filename' => 'phone shot.png', 'mime_type' => 'image/png']);
    Storage::disk('local')->put($image->storage_path, 'png-bytes');

    $response = $this->actingAs(Member::factory()->create())
        ->get("/feedback/comment-images/{$image->id}?download=1")
        ->assertOk()
        ->assertDownload('phone shot.png')
        ->assertHeader('Content-Type', 'image/png')
        ->assertHeader('X-Content-Type-Options', 'nosniff');

    expect($response->streamedContent())->toBe('png-bytes');
});

it('never serves a comment image of another type inline', function () {
    $image = FeedbackCommentImage::factory()->create(['original_filename' => 'drawing.svg', 'mime_type' => 'image/svg+xml']);
    Storage::disk('local')->put($image->storage_path, '<svg/>');

    $this->actingAs(Member::factory()->create())
        ->get("/feedback/comment-images/{$image->id}")
        ->assertOk()
        ->assertDownload('drawing.svg')
        ->assertHeader('X-Content-Type-Options', 'nosniff');
});

it('refuses both the inline and the download request when the policy denies', function () {
    $image = FeedbackCommentImage::factory()->create();
    Storage::disk('local')->put($image->storage_path, 'png-bytes');
    Gate::before(fn (Member $actor, string $ability) => $ability === 'download' ? false : null);
    $this->actingAs(Member::factory()->create());

    $this->get("/feedback/comment-images/{$image->id}")->assertForbidden();
    $this->get("/feedback/comment-images/{$image->id}?download=1")->assertForbidden();
});

it('serves a comment image at its French twin', function () {
    $image = FeedbackCommentImage::factory()->create(['original_filename' => 'capture.png']);
    Storage::disk('local')->put($image->storage_path, 'png-bytes');
    $item = $image->feedbackComment->feedback_item_id;
    $this->actingAs(Member::factory()->create());

    $this->withLocaleRoutes('fr', function () use ($image, $item) {
        $this->get("/fr/retroaction/{$item}")
            ->assertInertia(fn (Assert $page) => $page
                ->where('comments.0.images.0.href', "/fr/retroaction/images-commentaires/{$image->id}"));

        $this->get("/fr/retroaction/images-commentaires/{$image->id}?download=1")
            ->assertOk()
            ->assertDownload('capture.png');
    });
});

it('returns 404 for a comment image whose file is gone', function () {
    $image = FeedbackCommentImage::factory()->create();

    $this->actingAs(Member::factory()->create())
        ->get("/feedback/comment-images/{$image->id}")
        ->assertNotFound();
});

it('sends a guest to login from the comment image route', function () {
    $image = FeedbackCommentImage::factory()->create();

    $this->get("/feedback/comment-images/{$image->id}")->assertRedirect(route('login'));
});

it('does not register the comment image route in production (route layer)', function () {
    $this->app->detectEnvironment(fn () => 'production');

    $router = $this->app['router'];
    $router->setRoutes(new RouteCollection);
    Route::middleware('web')->group(base_path('routes/web.php'));

    expect(Route::has('feedback.comment-images.download'))->toBeFalse();
});

it('returns 404 from the comment image action in production (controller layer)', function () {
    $image = FeedbackCommentImage::factory()->create();
    Storage::disk('local')->put($image->storage_path, 'png-bytes');
    $this->app->detectEnvironment(fn () => 'production');

    $this->actingAs(Member::factory()->create())
        ->get("/feedback/comment-images/{$image->id}")
        ->assertNotFound();
});
