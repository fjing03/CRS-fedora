<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/* SDD notifications-real-bridge (D5): the pending-request reminder —
   one `awaiting` row per PL per stale pending request; deduped until
   read. Runs daily; the demo runs it on demand. */
Schedule::command('notifications:remind-pending')
    ->daily()->at('08:00');

/* Auto-reject rule (user brief 2026-11-06): a pending request whose proposed
   start is within 3 working hours (Mon–Fri 08:30–17:30, holidays excluded) can
   no longer be decided in time, so the sweep rejects it. Five minutes keeps the
   boundary honest without a per-request timer.

   2026-11-12 (user decision): the daemon now HAS a runner. A user crontab line
   runs `schedule:run` every minute with `DB_DATABASE=class_replacement_testing`,
   so the sweep fires on the DISPOSABLE testing database only. The demo
   databases stay manual (`php artisan requests:auto-reject --dry-run` first),
   because an automatic sweep there would write on a schedule — see
   docs/demo-db-data-changes.md (2026-11-12 entry). */
Schedule::command('requests:auto-reject')
    ->everyFiveMinutes()
    ->withoutOverlapping();
