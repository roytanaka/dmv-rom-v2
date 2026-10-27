<?php

use App\Mail\FeedbackCommentPosted;
use App\Mail\FeedbackFollowing;
use App\Models\FeedbackItem;
use App\Models\Member;
use Illuminate\Http\UploadedFile;
use Illuminate\Mail\MailManager;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;

/*
 * Feedback comment emails (#860, spec #856, ADR-0029 §15). Each new comment on a Feedback item
 * emails the item's Followers, except the commenter's own address. The comment form has the
 * same "Email me updates" box as the send form, so a Tester can follow an item someone else
 * sent. A status change sends nothing.
 *
 * Asserted from outside: the HTTP response, the emails sent (Mail::fake), and what a later
 * request can see.
 */

beforeEach(function () {
    $this->migrateFeedbackDatabase();
    Mail::fake();
});

// A valid comment, without the box ticked.
function commentPayload(array $overrides = []): array
{
    return array_merge([
        'tester_name' => 'Sam Operator',
        'body' => 'Fixed in today’s deploy.',
    ], $overrides);
}

it('emails every Follower of the item through the smtp mailer', function () {
    $item = FeedbackItem::factory()->create();
    $item->follow('pat@example.com', 'en');
    $item->follow('lee@example.com', 'en');

    $this->actingAs(Member::factory()->create())
        ->from("/feedback/{$item->id}")
        ->post("/feedback/{$item->id}/comments", commentPayload())
        ->assertRedirect("/feedback/{$item->id}")
        ->assertSessionHasNoErrors();

    Mail::assertSentCount(2);
    foreach (['pat@example.com', 'lee@example.com'] as $address) {
        Mail::assertSent(FeedbackCommentPosted::class, fn (FeedbackCommentPosted $mail) => $mail->hasTo($address)
            && $mail->mailer === 'smtp'
            && $mail->item->is($item));
    }
});

it('carries the comment text, the commenter’s name, the status, the item link and the stop link', function () {
    $item = FeedbackItem::factory()->create(['type' => 'bug', 'message' => 'Nothing saves.', 'status' => 'confirmed']);
    $follower = $item->follow('pat@example.com', 'en');

    $this->actingAs(Member::factory()->create())
        ->post("/feedback/{$item->id}/comments", commentPayload([
            'tester_name' => 'Sam Operator',
            'body' => "Fixed in today’s deploy.\nPlease try again.",
        ]));

    Mail::assertSent(FeedbackCommentPosted::class, function (FeedbackCommentPosted $mail) use ($item, $follower) {
        $mail->assertHasSubject("New comment on Feedback #{$item->id} Bug: Nothing saves.");
        $mail->assertSeeInHtml('Sam Operator');
        $mail->assertSeeInHtml('Fixed in today’s deploy.');
        $mail->assertSeeInHtml('Please try again.');
        $mail->assertSeeInHtml('Status: Confirmed');
        $mail->assertSeeInHtml("http://localhost/feedback/{$item->id}");
        $mail->assertSeeInHtml("http://localhost/feedback/followers/{$follower->id}/stop?signature=", false);
        $mail->assertSeeInHtml('To reply, add a comment on the site.');

        expect($mail->replyTo)->toBe([])
            ->and($mail->from)->toBe([])
            ->and($mail->attachments)->toBe([])
            ->and($mail->rawAttachments)->toBe([]);

        return true;
    });
});

it('shows markdown in the comment, the name and the item as plain text, with no link or image from it', function () {
    $item = FeedbackItem::factory()->create(['type' => 'bug', 'message' => '[Item](https://evil.example/item) breaks']);
    $item->follow('pat@example.com', 'en');

    $this->actingAs(Member::factory()->create())
        ->post("/feedback/{$item->id}/comments", commentPayload([
            'tester_name' => '[Sam](https://evil.example/name)',
            'body' => "Try [this](https://evil.example/body)\n![](https://px.example/p.png)\n<https://evil.example/auto>\n# Big",
        ]));

    Mail::assertSent(FeedbackCommentPosted::class, function (FeedbackCommentPosted $mail) {
        $html = $mail->render();

        expect($html)->not->toContain('href="https://evil.example')
            ->and($html)->not->toContain('<img')
            ->and($html)->not->toContain('<h1>Big')
            ->and($html)->toContain('[Item](https://evil.example/item) breaks')
            ->and($html)->toContain('[Sam](https://evil.example/name) commented:')
            ->and($html)->toContain('Try [this](https://evil.example/body)')
            ->and($html)->toContain('![](https://px.example/p.png)')
            ->and($html)->toContain('&lt;https://evil.example/auto&gt;')
            ->and($html)->toContain('# Big');

        return true;
    });
});

it('makes a commenter who ticks the box a Follower, with a receipt and the address shown', function () {
    $item = FeedbackItem::factory()->create();

    $this->actingAs(Member::factory()->create())
        ->from("/feedback/{$item->id}")
        ->post("/feedback/{$item->id}/comments", commentPayload(['follow' => true, 'follower_email' => ' Pat@Example.com ']))
        ->assertRedirect("/feedback/{$item->id}")
        ->assertSessionHasNoErrors();

    Mail::assertSentCount(1);
    Mail::assertSent(FeedbackFollowing::class, fn (FeedbackFollowing $mail) => $mail->hasTo('pat@example.com')
        && $mail->mailer === 'smtp'
        && $mail->item->is($item));

    $this->get("/feedback/{$item->id}")
        ->assertInertia(fn (Assert $page) => $page->where('flash.feedbackFollowing', 'pat@example.com'));

    // A later comment by someone else reaches the new Follower.
    $this->post("/feedback/{$item->id}/comments", commentPayload(['tester_name' => 'Lee']));

    Mail::assertSent(FeedbackCommentPosted::class, fn (FeedbackCommentPosted $mail) => $mail->hasTo('pat@example.com'));
});

it('sends no second receipt and keeps one Follower when the same address follows again', function () {
    $item = FeedbackItem::factory()->create();
    $this->actingAs(Member::factory()->create());

    $this->post("/feedback/{$item->id}/comments", commentPayload(['follow' => true, 'follower_email' => 'pat@example.com']));
    $this->withLocaleRoutes('fr', fn () => $this->post(
        "/fr/retroaction/{$item->id}/commentaires",
        commentPayload(['follow' => true, 'follower_email' => '  PAT@example.COM']),
    )->assertSessionHasNoErrors());

    Mail::assertSent(FeedbackFollowing::class, 1);
    Mail::assertNotSent(FeedbackCommentPosted::class);
    expect($item->followers()->sole())
        ->email->toBe('pat@example.com')
        ->locale->toBe('en');
});

it('sends no email to the commenter about their own comment, but still to the others', function () {
    $item = FeedbackItem::factory()->create();
    $item->follow('pat@example.com', 'en');
    $item->follow('lee@example.com', 'en');

    $this->actingAs(Member::factory()->create())
        ->post("/feedback/{$item->id}/comments", commentPayload(['follow' => true, 'follower_email' => 'PAT@example.com ']));

    Mail::assertSentCount(1);
    Mail::assertSent(FeedbackCommentPosted::class, fn (FeedbackCommentPosted $mail) => $mail->hasTo('lee@example.com'));
});

it('requires a valid email of at most 254 characters when the box is ticked on a comment', function (mixed $email) {
    $item = FeedbackItem::factory()->create();
    $item->follow('lee@example.com', 'en');

    $this->actingAs(Member::factory()->create())
        ->post("/feedback/{$item->id}/comments", commentPayload(['follow' => true, 'follower_email' => $email]))
        ->assertSessionHasErrors('follower_email');

    expect($item->comments()->count())->toBe(0)
        ->and($item->followers()->count())->toBe(1);
    Mail::assertNothingSent();
})->with([
    'missing' => '',
    'not an email' => 'pat-at-example',
    'too long' => str_repeat('a', 243).'@example.com',
]);

it('ignores the email when the box is not ticked on a comment', function () {
    $item = FeedbackItem::factory()->create();

    $this->actingAs(Member::factory()->create())
        ->post("/feedback/{$item->id}/comments", commentPayload(['follow' => false, 'follower_email' => 'not an email']))
        ->assertSessionHasNoErrors();

    expect($item->comments()->count())->toBe(1)
        ->and($item->followers()->count())->toBe(0);
    Mail::assertNothingSent();
});

it('saves the comment, reports the error, and still emails the other Followers when one send fails', function () {
    // A real smtp mailer whose transport refuses one address: Mail::fake() never reaches a
    // transport. The addresses it did deliver to are kept on the transport.
    $transport = new class extends AbstractTransport
    {
        /** @var list<string> */
        public array $delivered = [];

        public function __toString(): string
        {
            return 'refuses-one';
        }

        protected function doSend(SentMessage $message): void
        {
            $to = $message->getEnvelope()->getRecipients()[0]->getAddress();

            if ($to === 'broken@example.com') {
                throw new TransportException('Connection could not be established with host.');
            }

            $this->delivered[] = $to;
        }
    };
    Mail::swap(new MailManager($this->app));
    Mail::extend('refuses-one', fn () => $transport);
    config(['mail.mailers.smtp' => ['transport' => 'refuses-one']]);
    Exceptions::fake();

    $item = FeedbackItem::factory()->create();
    $item->follow('broken@example.com', 'en');
    $item->follow('lee@example.com', 'en');

    $this->actingAs(Member::factory()->create())
        ->from("/feedback/{$item->id}")
        ->post("/feedback/{$item->id}/comments", commentPayload())
        ->assertRedirect("/feedback/{$item->id}")
        ->assertSessionHasNoErrors();

    expect($item->comments()->count())->toBe(1)
        ->and($transport->delivered)->toBe(['lee@example.com']);
    Exceptions::assertReported(TransportException::class);
});

it('sends no email on a status change', function () {
    $item = FeedbackItem::factory()->create();
    $item->follow('pat@example.com', 'en');

    $this->actingAs(Member::factory()->operator()->create())
        ->patch("/feedback/{$item->id}/status", ['status' => 'confirmed'])
        ->assertSessionHasNoErrors();

    Mail::assertNothingSent();
});

it('points to the item for the images of a comment that has them, without attaching them', function () {
    Storage::fake('local');
    $item = FeedbackItem::factory()->create();
    $item->follow('pat@example.com', 'en');

    $this->actingAs(Member::factory()->create())
        ->post("/feedback/{$item->id}/comments", commentPayload([
            'body' => null,
            'images' => [UploadedFile::fake()->image('error.png')],
        ]))
        ->assertSessionHasNoErrors();

    Mail::assertSent(FeedbackCommentPosted::class, function (FeedbackCommentPosted $mail) {
        $mail->assertSeeInHtml('This comment has images. View the item to see them.');

        expect($mail->attachments)->toBe([])
            ->and($mail->rawAttachments)->toBe([]);

        return true;
    });
});

it('emails a French Follower in French, with French links', function () {
    $item = FeedbackItem::factory()->create(['type' => 'bug', 'message' => 'Rien ne marche.', 'status' => 'confirmed']);
    $follower = $item->follow('pat@example.com', 'fr');

    $this->actingAs(Member::factory()->create())
        ->post("/feedback/{$item->id}/comments", commentPayload(['tester_name' => 'Sam']));

    Mail::assertSent(FeedbackCommentPosted::class, function (FeedbackCommentPosted $mail) use ($item, $follower) {
        $mail->assertHasSubject("Nouveau commentaire sur la rétroaction nº {$item->id} Bogue : Rien ne marche.");
        $mail->assertSeeInHtml('Sam a commenté');
        $mail->assertSeeInHtml('Statut : Confirmé');
        $mail->assertSeeInHtml("http://localhost/fr/retroaction/{$item->id}");
        $mail->assertSeeInHtml("http://localhost/fr/retroaction/abonnes/{$follower->id}/arreter?signature=", false);

        return true;
    });
});

// --- Beta: the account's email (#861) ------------------------------------------

it('on Beta, sends the commenter no email about their own comment, matched on the account’s email, ticked or not', function (array $follow) {
    $this->forceAccountIdentity();
    $member = Member::factory()->create(['email' => 'Pat@Example.com']);
    $item = FeedbackItem::factory()->create(['member_email' => $member->email]);
    $item->follow('pat@example.com', 'en');
    $item->follow('lee@example.com', 'en');

    $this->actingAs($member)
        ->post("/feedback/{$item->id}/comments", commentPayload($follow))
        ->assertSessionHasNoErrors();

    Mail::assertSentCount(1);
    Mail::assertSent(FeedbackCommentPosted::class, fn (FeedbackCommentPosted $mail) => $mail->hasTo('lee@example.com'));
})->with([
    'box not ticked' => [[]],
    'box ticked, with someone else’s address typed' => [['follow' => true, 'follower_email' => 'lee@example.com']],
]);

it('on Beta, follows from a comment with the account’s email and ignores a typed one', function () {
    $this->forceAccountIdentity();
    $member = Member::factory()->create(['email' => 'Pat@Example.com']);
    $item = FeedbackItem::factory()->create(['member_email' => $member->email]);

    $this->actingAs($member)
        ->from("/feedback/{$item->id}")
        ->post("/feedback/{$item->id}/comments", commentPayload(['follow' => true, 'follower_email' => 'not an email']))
        ->assertSessionHasNoErrors();

    expect($item->followers()->sole()->email)->toBe('pat@example.com');
    Mail::assertSentCount(1);
    Mail::assertSent(FeedbackFollowing::class, fn (FeedbackFollowing $mail) => $mail->hasTo('pat@example.com'));

    $this->get("/feedback/{$item->id}")
        ->assertInertia(fn (Assert $page) => $page->where('flash.feedbackFollowing', 'pat@example.com'));
});
