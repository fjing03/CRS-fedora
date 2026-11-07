<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * SDD replacement-span-persistence T4 (user 2026-11-07): fill
 * `replacement_requests.replacement_span` for rows that predate the column.
 *
 * Why the audit log: the junction rows that carried a request's block width are
 * DELETEd by every release action, but the audit trail still counts them —
 * `submitted` is written once per cell, and every decision entry carries the
 * whole cell list in `details->'slots'`. So the width is recoverable exactly.
 *
 * Safe by construction:
 *  - DRY RUN by default; nothing is written without `--commit`.
 *  - Only `replacement_span IS NULL` rows are read — a row that already carries
 *    a width is never re-written (no drift on re-runs).
 *  - A width outside 1..8 is refused and reported, never stored.
 *  - The demo databases (`*_fresh`, `*_demo`) refuse to write without `--force`,
 *    because their changes need the snapshot + receipt ritual.
 */
final class BackfillReplacementSpan extends Command
{
    protected $signature = 'crs:backfill-replacement-span
        {--commit : Actually write (default is a dry run)}
        {--force : Allow writing to a demo database (needs the snapshot ritual first)}';

    protected $description = 'Fill replacement_requests.replacement_span for old rows, using the audit log';

    public function handle(): int
    {
        $database = (string) DB::connection()->getDatabaseName();
        $isDemo = str_ends_with($database, '_fresh') || str_ends_with($database, '_demo');
        $commit = (bool) $this->option('commit');

        $this->line('Database: <info>'.$database.'</info>');

        if ($commit && $isDemo && ! (bool) $this->option('force')) {
            $this->error('Refused: '.$database.' is a DEMO database. Snapshot it, log the change in docs/demo-db-data-changes.md, then re-run with --force.');

            return self::FAILURE;
        }

        $rows = DB::table('replacement_requests')
            ->whereNull('replacement_span')
            ->orderBy('id')
            ->get(['id', 'status', 'replacement_time_slot_id']);

        if ($rows->isEmpty()) {
            $this->line('Every request already carries a span. Nothing to do.');

            return self::SUCCESS;
        }

        $plan = [];
        $unresolved = [];

        foreach ($rows as $row) {
            $span = $this->spanFromAudit((int) $row->id);
            if ($span === null) {
                $unresolved[] = (int) $row->id;

                continue;
            }
            $plan[(int) $row->id] = $span;
        }

        $this->table(
            ['id', 'status', 'span'],
            $rows->map(fn ($r): array => [
                (int) $r->id,
                (string) $r->status,
                $plan[(int) $r->id] ?? 'unresolved',
            ])->all(),
        );

        if (! $commit) {
            $this->line('DRY RUN — '.count($plan).' row(s) would be filled'
                .($unresolved === [] ? '.' : '; unresolved: '.implode(', ', $unresolved).'.'));

            return self::SUCCESS;
        }

        $written = 0;
        foreach ($plan as $id => $span) {
            $written += DB::table('replacement_requests')
                ->where('id', $id)
                ->whereNull('replacement_span')
                ->update(['replacement_span' => $span]);
        }

        $this->line('Wrote '.$written.' row(s)'
            .($unresolved === [] ? '.' : '; unresolved (left null): '.implode(', ', $unresolved).'.'));

        return self::SUCCESS;
    }

    /**
     * The decision audit entry's own cell list first (exact), then the count of
     * `submitted` entries (one per cell). Null when neither exists.
     */
    private function spanFromAudit(int $requestId): ?int
    {
        $decision = DB::table('audit_logs')
            ->where('replacement_request_id', $requestId)
            ->whereIn('action', ['approved', 'rejected', 'cancelled'])
            ->whereNotNull('details')
            ->orderBy('id')
            ->value('details');

        if (is_string($decision) && $decision !== '') {
            $decoded = json_decode($decision, true);
            if (is_array($decoded) && isset($decoded['slots']) && is_array($decoded['slots'])) {
                return $this->sane(count($decoded['slots']));
            }
        }

        $submitted = (int) DB::table('audit_logs')
            ->where('replacement_request_id', $requestId)
            ->where('action', 'submitted')
            ->count();

        return $this->sane($submitted);
    }

    private function sane(int $span): ?int
    {
        return $span >= 1 && $span <= 8 ? $span : null;
    }
}
