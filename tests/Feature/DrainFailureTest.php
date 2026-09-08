<?php

use App\Console\Commands\DrainDeliveries;
use App\Enums\DeliveryState;
use App\Models\Delivery;
use App\Models\Member;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mailer\Transport\Smtp\SmtpTransport;

/*
 * The Drain's failure handling (#482, spec #479, ADR-0024 §4 and §10). The happy path — the
 * throttle, the slice, the locale — lives in SignUpCancellationMailTest; this file covers what
 * the Drain does when the host will not talk to it, or an address bounces at the door: the two
 * failure classes, the retry schedule, expiry, and the two status cache keys the pass writes.
 *
 * Both classes are provoked with a real mailer whose Symfony transport throws — a connection
 * transport that refuses to start, and a send transport that throws a chosen SMTP reply code —
 * so the Drain exercises the same catch it will in production. Mail::fake() cannot help here: it
 * never reaches a transport, so it can never throw one.
 */

/** A connection-level transport: {@see SmtpTransport::start()} throws, as a dead host does. */
final class RefusesToConnectTransport extends SmtpTransport
{
    public function start(): void
    {
        throw new TransportException('Connection could not be established with host.');
    }

    protected function doSend(SentMessage $message): void {}
}

/** A send-level transport: it connects, then throws the given SMTP reply code on every send. */
final class RejectsEverySendTransport extends AbstractTransport
{
    public function __construct(private readonly int $code, private readonly string $reply)
    {
        parent::__construct();
    }

    public function __toString(): string
    {
        return 'rejects-every-send';
    }

    protected function doSend(SentMessage $message): void
    {
        throw new TransportException($this->reply, $this->code);
    }
}

/** Point the default mailer at a transport that refuses the initial connect. */
function useRefusingConnection(): void
{
    Mail::extend('refusing', fn () => new RefusesToConnectTransport);
    config(['mail.mailers.refusing' => ['transport' => 'refusing'], 'mail.default' => 'refusing']);
}

/** Point the default mailer at a transport that throws the given reply code on send. */
function useRejectingSend(int $code, string $reply = 'rejected'): void
{
    Mail::extend('rejecting', fn () => new RejectsEverySendTransport($code, $reply));
    config(['mail.mailers.rejecting' => ['transport' => 'rejecting'], 'mail.default' => 'rejecting']);
}

it('writes the scheduler-last-ran cache key on every pass, even an empty queue', function () {
    expect(Cache::get(DrainDeliveries::SCHEDULER_LAST_RAN))->toBeNull();

    $this->freezeTime();
    $this->artisan('mail:drain')->assertSuccessful();

    expect(Cache::get(DrainDeliveries::SCHEDULER_LAST_RAN))->not->toBeNull()
        ->and(Cache::get(DrainDeliveries::SCHEDULER_LAST_RAN)->equalTo(now()))->toBeTrue();
});

it('marks a pending row past its expiry as expired and never sends it', function () {
    Mail::fake();

    $member = Member::factory()->create();
    $expired = Delivery::factory()->create([
        'member_id' => $member->id,
        'expires_at' => now()->subMinute(),
    ]);
    $live = Delivery::factory()->create([
        'member_id' => $member->id,
        'expires_at' => now()->addHour(),
    ]);

    $this->artisan('mail:drain')->assertSuccessful();

    expect($expired->refresh()->state)->toBe(DeliveryState::Expired)
        ->and($expired->sent_at)->toBeNull()
        ->and($live->refresh()->state)->toBe(DeliveryState::Sent);
});

it('leaves every row pending on a connect failure, records the error, and reports it', function () {
    Exceptions::fake();
    useRefusingConnection();
    $this->freezeTime();

    $member = Member::factory()->create();
    Delivery::factory()->count(3)->create(['member_id' => $member->id]);

    $this->artisan('mail:drain')->assertSuccessful();

    // No give-up: the whole slice is untouched, ready for the next minute.
    expect(Delivery::where('state', DeliveryState::Pending)->count())->toBe(3);

    $error = Cache::get(DrainDeliveries::LAST_CONNECTION_ERROR);
    expect($error['message'])->toContain('Connection could not be established')
        ->and($error['at']->equalTo(now()))->toBeTrue();

    Exceptions::assertReported(fn (TransportException $e) => str_contains($e->getMessage(), 'Connection could not be established'));
});

it('fails a row on the first attempt when the host answers with a 5xx code', function () {
    useRejectingSend(550, '550 5.1.1 mailbox unavailable');
    $this->freezeTime();

    $delivery = Delivery::factory()->create();

    $this->artisan('mail:drain')->assertSuccessful();

    $delivery->refresh();
    expect($delivery->state)->toBe(DeliveryState::Failed)
        ->and($delivery->attempts)->toBe(1)
        ->and($delivery->failed_at)->not->toBeNull()
        ->and($delivery->last_error)->toContain('mailbox unavailable');
});

it('retries a 4xx row now, then +10 minutes, then +1 hour, then fails it on the fourth try', function () {
    useRejectingSend(421, '421 4.7.0 try again later');
    $this->freezeTime();

    $delivery = Delivery::factory()->create();
    $fmt = fn ($t) => $t?->format('Y-m-d H:i:s');

    // First failure: retried on the next pass — next attempt is now.
    $this->artisan('mail:drain')->assertSuccessful();
    $delivery->refresh();
    expect($delivery->state)->toBe(DeliveryState::Pending)
        ->and($delivery->attempts)->toBe(1)
        ->and($fmt($delivery->next_attempt_at))->toBe($fmt(now()))
        ->and($delivery->last_error)->toContain('try again later');

    // Second failure (still due): next attempt is 10 minutes out.
    $this->artisan('mail:drain')->assertSuccessful();
    $delivery->refresh();
    expect($delivery->attempts)->toBe(2)
        ->and($fmt($delivery->next_attempt_at))->toBe($fmt(now()->addMinutes(10)));

    // Third failure, once the 10 minutes have passed: next attempt is an hour out.
    $this->travel(10)->minutes();
    $this->artisan('mail:drain')->assertSuccessful();
    $delivery->refresh();
    expect($delivery->attempts)->toBe(3)
        ->and($fmt($delivery->next_attempt_at))->toBe($fmt(now()->addHour()));

    // Fourth failure, an hour later: the row gives up.
    $this->travel(1)->hour();
    $this->artisan('mail:drain')->assertSuccessful();
    $delivery->refresh();
    expect($delivery->state)->toBe(DeliveryState::Failed)
        ->and($delivery->attempts)->toBe(4)
        ->and($delivery->failed_at)->not->toBeNull();
});

it('treats a connection dropped mid-message — a transport exception with no code — as temporary', function () {
    useRejectingSend(0, 'Connection to host has been closed unexpectedly.');
    $this->freezeTime();

    $delivery = Delivery::factory()->create();

    $this->artisan('mail:drain')->assertSuccessful();

    $delivery->refresh();
    expect($delivery->state)->toBe(DeliveryState::Pending)
        ->and($delivery->attempts)->toBe(1)
        ->and($delivery->next_attempt_at->format('Y-m-d H:i:s'))->toBe(now()->format('Y-m-d H:i:s'));
});

it('writes only the scheduler heartbeat on a clean pass — no connection-error key', function () {
    Mail::fake();

    Delivery::factory()->create();

    $this->artisan('mail:drain')->assertSuccessful();

    expect(Cache::get(DrainDeliveries::SCHEDULER_LAST_RAN))->not->toBeNull()
        ->and(Cache::get(DrainDeliveries::LAST_CONNECTION_ERROR))->toBeNull();
});
