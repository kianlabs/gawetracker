<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Pull new JobStreet/Glints mail every 30 minutes once Gmail is connected.
// The command no-ops (exit 0) when no account is linked, so it is safe to
// schedule unconditionally. Requires `php artisan schedule:work` (or a cron
// entry running `php artisan schedule:run` every minute) in production.
Schedule::command('emails:import --gmail --max=50')
    ->everyThirtyMinutes()
    ->withoutOverlapping()
    ->runInBackground();
