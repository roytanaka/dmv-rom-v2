<?php

use App\Mail\FeedbackFollowing;
use App\Mail\FeedbackItemPosted;
use App\Models\FeedbackItem;
use App\Models\Member;
use Illuminate\Http\UploadedFile;
use Illuminate\Mail\MailManager;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;

/*
 * The notify address (ADR-0029 §16). Each new Feedback item is emailed to the address in
 * `mail.feedback_notify_address`. Empty sends nothing. Comments do not go there.
 *
 * Asserted from outside: the HTTP response and the emails sent (Mail::fake).
 */

beforeEach(function () {
    $this->migrateFeedbackDatabase();
    Mail::fake();
    config(['mail.feedback_notify_address' => 'operator@example.com']);
});

// A valid send, without the box ticked.
function notifyPayload(array $overrides = []): array
{
    return array_merge([
        'tester_name' => 'Pat Tester',
        'type' => 'bug',
        'message' => 'The Save button does nothing.',
        'page_url' => '/dashboard',
    ], $overrides);
}

it('emails each new item to the notify address through the smtp mailer', function () {
    $this->actingAs(Member::factory()->create())
        ->from('/dashboard')
        ->post('/feedback', notifyPayload())
        ->assertRedirect('/dashboard')
        ->assertSessionHasNoErrors();

    $item = FeedbackItem::sole();

    Mail::assertSentCount(1);
    Mail::assertSent(FeedbackItemPosted::class, fn (FeedbackItemPosted $mail) => $mail->hasTo('operator@example.com')
        && $mail->mailer === 'smtp'
        && $mail->item->is($item));
});

it('carries the Tester’s name, the page, the message and the item link, with no stop link', function () {
    Storage::fake('local');

    $this->actingAs(Member::factory()->create())
        ->post('/feedback', notifyPayload([
            'message' => "Nothing saves.\nI pressed it **twice**.",
            'screenshots' => [UploadedFile::fake()->image('save.png')],
        ]));

    $item = FeedbackItem::sole();

    Mail::assertSent(FeedbackItemPosted::class, function (FeedbackItemPosted $mail) use ($item) {
        $mail->assertHasSubject("New Feedback #{$item->id} Bug: Nothing saves. I pressed it **twice**.");
        $mail->assertSeeInHtml('Pat Tester sent this from /dashboard:', false);
        $mail->assertSeeInHtml('Nothing saves.');
        $mail->assertSeeInHtml('I pressed it **twice**.', false);
        $mail->assertSeeInHtml('This item has screenshots.');
        $mail->assertSeeInHtml("http://localhost/feedback/{$item->id}");
        $mail->assertDontSeeInHtml('/stop?signature=', false);

        expect($mail->replyTo)->toBe([]);

        return true;
    });
});

it('sends in English with English links when the item was sent from a French page', function () {
    $this->withLocaleRoutes('fr', function () {
        $this->actingAs(Member::factory()->create())
            ->post('/fr/retroaction', notifyPayload())
            ->assertSessionHasNoErrors();
    });

    $item = FeedbackItem::sole();

    Mail::assertSent(FeedbackItemPosted::class, function (FeedbackItemPosted $mail) use ($item) {
        // The subject assertion reads the envelope outside the mail's locale, so check the locale
        // and the rendered body, which renders in it.
        expect($mail->locale)->toBe('en');
        $mail->assertSeeInHtml('New Feedback #');
        $mail->assertSeeInHtml("http://localhost/feedback/{$item->id}");

        return true;
    });
});

it('sends both the receipt and the notify email when the Tester follows', function () {
    $this->actingAs(Member::factory()->create())
        ->post('/feedback', notifyPayload(['follow' => true, 'follower_email' => 'pat@example.com']));

    Mail::assertSentCount(2);
    Mail::assertSent(FeedbackFollowing::class, fn ($mail) => $mail->hasTo('pat@example.com'));
    Mail::assertSent(FeedbackItemPosted::class, fn ($mail) => $mail->hasTo('operator@example.com'));
});

it('sends nothing when the notify address is empty', function (?string $address) {
    config(['mail.feedback_notify_address' => $address]);

    $this->actingAs(Member::factory()->create())
        ->post('/feedback', notifyPayload())
        ->assertSessionHasNoErrors();

    expect(FeedbackItem::count())->toBe(1);
    Mail::assertNothingSent();
})->with([null, '']);

it('does not email a comment to the notify address', function () {
    $item = FeedbackItem::factory()->create();

    $this->actingAs(Member::factory()->create())
        ->post("/feedback/{$item->id}/comments", ['tester_name' => 'Sam Operator', 'body' => 'Seen it.'])
        ->assertSessionHasNoErrors();

    Mail::assertNothingSent();
});

it('saves the item and reports the error when the notify email cannot be sent', function () {
    // A real smtp mailer whose transport throws: Mail::fake() never reaches a transport.
    Mail::swap(new MailManager($this->app));
    Mail::extend('throwing', fn () => new class extends AbstractTransport
    {
        public function __toString(): string
        {
            return 'throwing';
        }

        protected function doSend(SentMessage $message): void
        {
            throw new TransportException('Connection could not be established with host.');
        }
    });
    config(['mail.mailers.smtp' => ['transport' => 'throwing']]);
    Exceptions::fake();

    $this->actingAs(Member::factory()->create())
        ->from('/dashboard')
        ->post('/feedback', notifyPayload())
        ->assertRedirect('/dashboard')
        ->assertSessionHasNoErrors();

    expect(FeedbackItem::count())->toBe(1);
    Exceptions::assertReported(TransportException::class);
});
