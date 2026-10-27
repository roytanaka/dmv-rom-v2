<?php

use App\Mail\FeedbackFollowing;
use App\Models\FeedbackItem;
use App\Models\Member;
use App\Support\FeedbackFollowerMail;
use Illuminate\Mail\MailManager;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;

/*
 * Feedback Followers (#857, spec #856, ADR-0029 §15). A Tester ticks "Email me updates" when
 * they send a Feedback item, types an address, and gets a "You're following" receipt. Every
 * Follower email carries a signed stop link to a page with one "Stop updates" button.
 *
 * Asserted from outside: the HTTP response, the emails sent (Mail::fake), and what a later
 * request can see.
 */

beforeEach(function () {
    $this->migrateFeedbackDatabase();
    Mail::fake();
});

// A valid send with the box ticked.
function followPayload(array $overrides = []): array
{
    return array_merge([
        'tester_name' => 'Pat Tester',
        'type' => 'bug',
        'message' => 'The Save button does nothing.',
        'page_url' => '/dashboard',
        'follow' => true,
        'follower_email' => 'pat@example.com',
    ], $overrides);
}

it('sends one receipt through the smtp mailer when the Tester follows on send', function () {
    $this->actingAs(Member::factory()->create())
        ->from('/dashboard')
        ->post('/feedback', followPayload())
        ->assertRedirect('/dashboard')
        ->assertSessionHasNoErrors();

    $item = FeedbackItem::sole();

    Mail::assertSentCount(1);
    Mail::assertSent(FeedbackFollowing::class, fn (FeedbackFollowing $mail) => $mail->hasTo('pat@example.com')
        && $mail->mailer === 'smtp'
        && $mail->item->is($item));
});

it('names the item by number, type and the start of its message, and links to it', function () {
    $this->actingAs(Member::factory()->create())
        ->post('/feedback', followPayload([
            'message' => 'The Save button on the Schedule page does nothing when I press it twice.',
        ]));

    $item = FeedbackItem::sole();

    Mail::assertSent(FeedbackFollowing::class, function (FeedbackFollowing $mail) use ($item) {
        $mail->assertHasSubject("You’re following Feedback #{$item->id} Bug: The Save button on the Schedule page does nothing...");
        $mail->assertSeeInHtml("http://localhost/feedback/{$item->id}");
        $mail->assertSeeInHtml("http://localhost/feedback/followers/{$item->followers()->sole()->id}/stop?signature=", false);

        return true;
    });
});

it('sends the receipt in French, with French links, to a Tester who followed from a French page', function () {
    $this->withLocaleRoutes('fr', function () {
        $this->actingAs(Member::factory()->create())
            ->post('/fr/retroaction', followPayload(['message' => 'Rien ne marche.']))
            ->assertSessionHasNoErrors();
    });

    $item = FeedbackItem::sole();
    $follower = $item->followers()->sole();

    expect($follower->locale)->toBe('fr');
    Mail::assertSent(FeedbackFollowing::class, function (FeedbackFollowing $mail) use ($item, $follower) {
        $mail->assertHasSubject("Vous suivez la rétroaction nº {$item->id} Bogue : Rien ne marche.");
        $mail->assertSeeInHtml('Vous suivez cette rétroaction');
        $mail->assertSeeInHtml("http://localhost/fr/retroaction/{$item->id}");
        $mail->assertSeeInHtml("http://localhost/fr/retroaction/abonnes/{$follower->id}/arreter?signature=", false);

        return true;
    });
});

it('saves the item and reports the error when the receipt cannot be sent', function () {
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
        ->post('/feedback', followPayload())
        ->assertRedirect('/dashboard')
        ->assertSessionHasNoErrors();

    expect(FeedbackItem::count())->toBe(1);
    Exceptions::assertReported(TransportException::class);
});

it('shows where the updates go after a send with the box ticked', function () {
    $this->actingAs(Member::factory()->create())
        ->from('/feedback')
        ->post('/feedback', followPayload(['follower_email' => ' Pat@Example.com']))
        ->assertRedirect('/feedback');

    $this->get('/feedback')
        ->assertInertia(fn (Assert $page) => $page->where('flash.feedbackFollowing', 'pat@example.com'));

    // One-shot: the next page no longer carries it.
    $this->get('/feedback')
        ->assertInertia(fn (Assert $page) => $page->where('flash.feedbackFollowing', null));
});

it('shows no Follower address or count on the list or the item page', function () {
    $item = FeedbackItem::factory()->create();
    $item->follow('secret.follower@example.com', 'en');

    $this->actingAs(Member::factory()->create());

    foreach (['/feedback', "/feedback/{$item->id}"] as $page) {
        $this->get($page)
            ->assertOk()
            ->assertDontSee('secret.follower@example.com')
            ->assertDontSee('followers_count')
            ->assertDontSee('followersCount');
    }
});

// --- Stop updates ------------------------------------------------------------

// Send an item with the box ticked as a fresh Member, log out, and return the stop link the
// receipt carries.
function followAndTakeStopLink(array $overrides = [], string $path = '/feedback'): string
{
    test()->actingAs(Member::factory()->create())->post($path, followPayload($overrides));
    auth()->logout();

    $html = Mail::sent(FeedbackFollowing::class)->last()->render();
    preg_match('#href="([^"]+signature=[^"]+)"#', $html, $match);

    return html_entity_decode($match[1]);
}

it('opens the stop page with one Stop updates button, without a login', function () {
    $link = followAndTakeStopLink(['message' => 'Nothing saves.']);
    $item = FeedbackItem::sole();

    $this->get($link)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('feedback/StopUpdates')
            ->where('state', 'confirm')
            ->where('itemLabel', "#{$item->id} Bug: Nothing saves.")
            ->where('stopHref', $link));
});

it('stops the updates when the button is pressed, and stays calm when pressed again', function () {
    $link = followAndTakeStopLink(['message' => 'Nothing saves.']);
    $item = FeedbackItem::sole();

    $this->post($link)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('feedback/StopUpdates')
            ->where('state', 'stopped')
            ->where('itemLabel', "#{$item->id} Bug: Nothing saves."));

    expect($item->followers()->count())->toBe(0);

    $this->post($link)->assertOk()->assertInertia(fn (Assert $page) => $page->where('state', 'already'));
    $this->get($link)->assertOk()->assertInertia(fn (Assert $page) => $page->where('state', 'already'));
});

it('stops only the one item', function () {
    $first = followAndTakeStopLink();
    followAndTakeStopLink();

    $this->post($first)->assertOk();

    expect(FeedbackItem::query()->withCount('followers')->orderBy('id')->pluck('followers_count')->all())->toBe([0, 1]);
});

it('rejects a tampered stop link', function () {
    $link = followAndTakeStopLink();
    $tampered = preg_replace('#signature=(.)#', 'signature=0', $link);
    $otherFollower = str_replace('/followers/1/', '/followers/2/', $link);

    $this->get($tampered)->assertForbidden();
    $this->post($tampered)->assertForbidden();
    $this->post($otherFollower)->assertForbidden();
    $this->post(strtok($link, '?'))->assertForbidden();

    expect(FeedbackItem::sole()->followers()->count())->toBe(1);
});

it('says the updates are already stopped when the item is gone', function () {
    $link = followAndTakeStopLink();
    $this->actingAs(Member::factory()->operator()->create())
        ->delete('/feedback/'.FeedbackItem::sole()->id);
    auth()->logout();

    $this->get($link)->assertOk()->assertInertia(fn (Assert $page) => $page->where('state', 'already'));
    $this->post($link)->assertOk()->assertInertia(fn (Assert $page) => $page->where('state', 'already'));
});

it('opens a French stop link in French', function () {
    $this->withLocaleRoutes('fr', function () {
        $link = followAndTakeStopLink(path: '/fr/retroaction');

        expect($link)->toContain('/fr/retroaction/abonnes/');
        $this->get($link)->assertOk()->assertInertia(fn (Assert $page) => $page->where('state', 'confirm'));
        $this->post($link)->assertOk()->assertInertia(fn (Assert $page) => $page->where('state', 'stopped'));
    });
});

it('requires a valid email of at most 254 characters when the box is ticked', function (mixed $email) {
    $this->actingAs(Member::factory()->create())
        ->post('/feedback', followPayload(['follower_email' => $email]))
        ->assertSessionHasErrors('follower_email');

    expect(FeedbackItem::count())->toBe(0);
    Mail::assertNothingSent();
})->with([
    'missing' => '',
    'not an email' => 'pat-at-example',
    'too long' => str_repeat('a', 243).'@example.com',
]);

it('ignores the email and sends nothing when the box is not ticked', function () {
    $this->actingAs(Member::factory()->create())
        ->post('/feedback', followPayload(['follow' => false, 'follower_email' => 'not an email']))
        ->assertSessionHasNoErrors();

    expect(FeedbackItem::count())->toBe(1);
    Mail::assertNothingSent();
});

it('keeps one Follower and sends no second receipt for the same address in another case or with spaces', function () {
    $this->actingAs(Member::factory()->create())
        ->post('/feedback', followPayload(['follower_email' => '  Pat@Example.COM ']))
        ->assertSessionHasNoErrors();

    $item = FeedbackItem::sole();
    FeedbackFollowerMail::receipt($item->follow('PAT@example.com  ', 'fr'));

    Mail::assertSentCount(1);
    Mail::assertSent(FeedbackFollowing::class, fn (FeedbackFollowing $mail) => $mail->hasTo('pat@example.com'));
    expect($item->followers()->sole())
        ->email->toBe('pat@example.com')
        ->locale->toBe('en');
});
