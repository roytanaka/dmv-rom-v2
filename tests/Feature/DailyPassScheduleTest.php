<?php

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;

/*
 * The daily pass schedule (#486, spec #479, ADR-0024 §10). The Reminder-writing pass runs once
 * a day at 06:00 in the org timezone, under a 10-minute overlap lock so a crashed pass cannot
 * jam the queue for the default 24 hours. Prior art: the every-minute Drain in the same file.
 */

/** The scheduled event whose command is the given Artisan signature, or null. */
function scheduledEventFor(string $signature): ?Event
{
    foreach (app(Schedule::class)->events() as $event) {
        if (str_contains($event->command ?? '', $signature)) {
            return $event;
        }
    }

    return null;
}

it('runs the daily pass at 06:00 in the org timezone', function () {
    $event = scheduledEventFor('mail:daily-pass');

    expect($event)->not->toBeNull();
    expect($event->expression)->toBe('0 6 * * *');
    expect($event->timezone)->toBe(config('app.org_timezone'));
});

it('locks the daily pass against overlap with a 10-minute expiry', function () {
    $event = scheduledEventFor('mail:daily-pass');

    expect($event->withoutOverlapping)->toBeTrue();
    expect($event->expiresAt)->toBe(10);
});
