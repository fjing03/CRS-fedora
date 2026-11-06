<?php

namespace App\Console\Commands;

use App\Services\PendingRequestExpiry;
use Illuminate\Console\Command;

/**
 * The auto-reject rule's trigger (user brief "Auto-Reject Pending Requests",
 * 2026-11-06): reject every PENDING request whose proposed start is within (or
 * exactly) 3 working hours of now, on the TARUMT Sabah calendar (Mon–Fri
 * 08:30–17:30, semester holidays excluded).
 *
 * Scheduled every five minutes (routes/console.php). `--dry-run` prints the same
 * answer without writing, which is how the rule is demonstrated safely.
 */
final class AutoRejectPendingRequests extends Command
{
    protected $signature = 'requests:auto-reject {--dry-run : Report what would be rejected without writing}';

    protected $description = 'Auto-reject pending requests whose proposed start is within 3 working hours';

    public function handle(PendingRequestExpiry $expiry): int
    {
        $result = $expiry->sweep((bool) $this->option('dry-run'));

        $due = $result['due'] === [] ? 'none' : implode(', ', $result['due']);

        if ((bool) $this->option('dry-run')) {
            $this->line('Scanned '.$result['scanned'].' pending request(s); would auto-reject: '.$due.'.');

            return self::SUCCESS;
        }

        $rejected = $result['rejected'] === [] ? 'none' : implode(', ', $result['rejected']);
        $this->line('Scanned '.$result['scanned'].' pending request(s); auto-rejected: '.$rejected.' (due: '.$due.').');

        return self::SUCCESS;
    }
}
