<?php

use App\Console\Commands\DrainDeliveries;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// The Drain (spec #479, ADR-0024) — every minute, empties a slice of the Delivery queue. The
// overlap lock (10-minute expiry, on the database cache store, which is this app's default)
// keeps a slow pass from double-sending and a crashed one from jamming the queue. The crontab
// line that runs the scheduler is a per-environment human step in the deploy docs.
Schedule::command(DrainDeliveries::class)
    ->everyMinute()
    ->withoutOverlapping(10);
