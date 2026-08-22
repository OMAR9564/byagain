<?php

declare(strict_types=1);

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function (): void {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|----------------------------------------------------------------------------
| Schedule
|----------------------------------------------------------------------------
*/

/*
 * The whole product runs on this one sweep. Every five minutes it finds the
 * users whose local clock has just reached their send time, builds their
 * review and queues their mail — which is how a single schedule serves every
 * timezone without a per-user cron to keep in sync (FR-089, SC-013).
 *
 * withoutOverlapping because a slow run must not be joined by the next one.
 * Correctness does not depend on it — the unique indexes already make a
 * duplicate run harmless — but two sweeps racing would waste the work.
 */
Schedule::command('byagain:dispatch-daily')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->runInBackground();
