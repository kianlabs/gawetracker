<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Keep the discovery list fresh: re-run every user's saved searches each hour.
// withoutOverlapping stops a slow run from stacking on the next tick.
Schedule::command('jobs:discover-saved')
    ->hourly()
    ->withoutOverlapping()
    ->runInBackground();

// Keep every user's application statuses in sync with their linked Gmail
// mailbox. The command no-ops (exit 0) when nobody has connected a mailbox,
// so it is safe to schedule unconditionally.
Schedule::command('emails:import --gmail')
    ->everyThirtyMinutes()
    ->withoutOverlapping()
    ->runInBackground();
