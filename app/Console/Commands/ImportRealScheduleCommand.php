<?php

namespace App\Console\Commands;

use Database\Seeders\RealScheduleSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

use function count;

/**
 * crs:import-real-schedule — the ONLY writer of real schedule records on the
 * demo DB (SDD change import-real-schedule-records; spec
 * real-schedule-importer R3 + R8).
 *
 * D1: this command owns the MODE GATE (R3.5); the write/verify core lives in
 * RealScheduleSeeder and runs ungated when chained from DatabaseSeeder.
 *
 * Mode gate (R3.1), by live class_sessions count:
 *   0   → import-additive  (fresh test DBs; DatabaseSeeder chains the core)
 *   35  → import-replace   (demo baseline: Phase 1 delete, then import)
 *   101 + dataset fingerprint (preflight R2.1 assertions — no file hashing)
 *       → verify-only (R3.3: no writes)
 *   anything else → abort with guidance
 *
 * --verify forces verify-only and aborts on non-imported state (R3.2).
 * Import mode on the imported state is refused — verify IS the re-run (R3.4).
 */
class ImportRealScheduleCommand extends Command
{
    protected $signature = 'crs:import-real-schedule {--verify : Verify the imported state without writing}';

    protected $description = 'Import the real 202505 schedule datasets (replaces hand-made sessions; SDD import-real-schedule-records)';

    public function handle(): int
    {
        $sessions = DB::table('class_sessions')->count();

        if ($this->option('verify')) {
            if ($sessions !== 101) {
                $this->error(sprintf(
                    '--verify requires the imported state (101 class_sessions); live count is %d. Aborting (nothing written).',
                    $sessions,
                ));

                return Command::FAILURE;
            }

            return $this->verifyOnly();
        }

        return match (true) {
            $sessions === 0 => $this->importAdditive(),
            $sessions === 35 => $this->importReplace(),
            $sessions === 101 => $this->verifyOnly(),
            default => $this->abortUnexpectedState($sessions),
        };
    }

    private function importReplace(): int
    {
        $this->info('Mode: import-replace (demo baseline detected — 35 hand-made sessions).');

        try {
            $seeder = new RealScheduleSeeder;
            $plan = $seeder->preflight();
            $this->echoLog($seeder);

            $seeder->deleteExisting();
            $this->echoLog($seeder);

            $seeder->importAll($plan);
            $seeder->verify();
            $this->echoLog($seeder);
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return Command::FAILURE;
        }

        return $this->finish();
    }

    private function importAdditive(): int
    {
        $this->info('Mode: import-additive (no existing sessions — fresh DB).');

        try {
            $seeder = new RealScheduleSeeder;
            $plan = $seeder->preflight();
            $this->echoLog($seeder);

            $seeder->importAll($plan);
            $seeder->verify();
            $this->echoLog($seeder);
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return Command::FAILURE;
        }

        return $this->finish();
    }

    private function verifyOnly(): int
    {
        $this->info('Mode: verify-only (no writes).');

        try {
            $seeder = new RealScheduleSeeder;
            $seeder->preflight(); // fingerprint: R2.1 dataset assertions (no writes)
            $this->echoLog($seeder);

            $seeder->verify();
            $this->echoLog($seeder);
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return Command::FAILURE;
        }

        $this->info('Verify PASSED — imported state intact.');

        return Command::SUCCESS;
    }

    private function abortUnexpectedState(int $sessions): int
    {
        $this->error(sprintf(
            'Unexpected state: %d class_sessions (expected 0, 35, or 101). '
            .'Nothing written. Restore from a backup or inspect manually before retrying.',
            $sessions,
        ));

        return Command::FAILURE;
    }

    private function finish(): int
    {
        $this->info('Import complete. Row-count snapshot (records-intact gate):');
        try {
            foreach (DbRowCountsCommand::rowCounts() as $table => $rows) {
                $this->line('  '.$table."\t".$rows);
            }
        } catch (RuntimeException $e) {
            $this->error('snapshot failed: '.$e->getMessage());

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    private function echoLog(RealScheduleSeeder $seeder): void
    {
        $lines = $seeder->logLines();
        for ($i = $this->echoedLogLines; $i < count($lines); $i++) {
            $line = $lines[$i];
            if (str_starts_with($line, 'WARNING:')) {
                $this->warn($line);

                continue;
            }
            $this->line($line);
        }
        $this->echoedLogLines = count($lines);
    }

    private int $echoedLogLines = 0;
}
