<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;

use function ksort;

/**
 * crs:db-row-counts — the standing "records-intact" gate (SDD change
 * import-real-schedule-records, design §7; spec records-intact-gate R1–R3).
 *
 * Enumerates every base table in the public schema from information_schema
 * (self-proving on schema drift — no hardcoded list, D11) and prints
 * table<TAB>rows, alphabetical. Read-only (R2.3); non-zero exit on any
 * failure (R2.2). The importer's final step reuses rowCounts() — one
 * implementation, no duplicated SQL (R3.1).
 */
class DbRowCountsCommand extends Command
{
    protected $signature = 'crs:db-row-counts';

    protected $description = 'Print row counts for every public table (records-intact gate)';

    public function handle(): int
    {
        try {
            $counts = self::rowCounts();
        } catch (RuntimeException $e) {
            $this->error('crs:db-row-counts failed: '.$e->getMessage());

            return Command::FAILURE;
        }

        foreach ($counts as $table => $rows) {
            $this->line($table."\t".$rows);
        }

        return Command::SUCCESS;
    }

    /**
     * Row counts for every public base table, alphabetical (R1.1/R1.2).
     *
     * @return array<string, int>
     *
     * @throws RuntimeException on connection or schema failure
     */
    public static function rowCounts(): array
    {
        try {
            $tables = DB::select(
                "SELECT table_name FROM information_schema.tables
                 WHERE table_schema = 'public' AND table_type = 'BASE TABLE'
                 ORDER BY table_name",
            );
        } catch (\Throwable $e) {
            throw new RuntimeException('schema enumeration failed: '.$e->getMessage(), 0, $e);
        }

        $counts = [];
        foreach ($tables as $table) {
            $name = $table->table_name;
            try {
                $counts[$name] = (int) DB::table($name)->count();
            } catch (\Throwable $e) {
                throw new RuntimeException("row count failed for {$name}: ".$e->getMessage(), 0, $e);
            }
        }
        ksort($counts);

        return $counts;
    }
}
