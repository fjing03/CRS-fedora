<?php

namespace App\Console\Commands;

use App\Models\ReplacementRequest;
use App\Models\User;
use App\Support\Notifications\NotificationEmitter;
use Illuminate\Console\Command;

/**
 * The pending-request reminder (SDD notifications-real-bridge D5): one
 * `awaiting` row per PL per pending request older than the threshold
 * (24 h default). Deduped until read — an UNREAD awaiting row for the
 * same (user, request) skips; a READ one re-arms on the next run.
 */
final class NotifyPendingRequests extends Command
{
    protected $signature = 'notifications:remind-pending {--hours=24}';

    protected $description = 'Create pending-request reminder notifications for PLs (deduped until read)';

    public function handle(NotificationEmitter $emitter): int
    {
        $hours = max(1, (int) $this->option('hours'));
        $before = now()->subHours($hours);

        $stale = ReplacementRequest::query()
            ->where('status', 'pending')
            ->where('submitted_at', '<', $before)
            ->with(['classSession.module', 'proposer'])
            ->get();

        $pls = User::query()
            ->whereHas('lecturer', fn ($q) => $q->where('is_pl', true))
            ->get();

        if ($stale->isEmpty() || $pls->isEmpty()) {
            $this->line('Nothing to remind.');

            return self::SUCCESS;
        }

        $created = 0;
        foreach ($stale as $request) {
            $session = $request->classSession;
            if ($session === null) {
                continue;
            }
            $emitter->pendingReminder(
                $pls,
                (string) ($session->module->module_code ?? ''),
                (string) $session->session_type,
                (string) ($request->proposer->name ?? ''),
                (int) $request->id,
                (int) $request->week_number,
            );
            $created++;
        }

        $this->line('Reminders queued for '.$created.' pending request(s) across '.$pls->count().' PL(s).');

        return self::SUCCESS;
    }
}
