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
