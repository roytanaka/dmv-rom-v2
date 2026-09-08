<?php

use App\Console\Commands\DrainDeliveries;
use App\Console\Commands\RunDailyMailPass;
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

// The daily pass (spec #479, ADR-0024 §7, §10) — 06:00 in the org timezone, writes the day's
// Reminder Deliveries; the Drain sends them within the hour. The same 10-minute overlap lock as
// the Drain guards it, so a crashed pass cannot jam the queue for the default 24 hours. It only
// writes rows and never talks SMTP; a throwing pass reports as an error rather than tripping the
// Drain's cron monitor.
Schedule::command(RunDailyMailPass::class)
    ->dailyAt('06:00')
    ->timezone(config('app.org_timezone'))
    ->withoutOverlapping(10);
