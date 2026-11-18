<?php

namespace Tests\Integration;

use App\Livewire\CohortTimetable;
use Illuminate\Database\Connection;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Tests\Support\CancelledSlotFixture;
use Tests\TestCase;

/** Run explicitly on a provisioned private PostgreSQL cluster, not the default suites. */
final class MyDayClassesMissingSlotTest extends TestCase
{
    public function test_day_list_keeps_other_rows_when_a_make_up_slot_disappears_between_reads(): void
    {
        $root = getenv('CRS_P2_PG_ROOT');
        $this->assertIsString($root, 'Set CRS_P2_PG_ROOT to the private PostgreSQL test root.');
        $this->assertMatchesRegularExpression('~\A/tmp/crs-p2-pg\.[a-zA-Z0-9]+\z~', $root);
        $this->assertSame('pgsql', DB::getDefaultConnection());

        config(['database.connections.p2_peer' => config('database.connections.pgsql')]);
        $reader = DB::connection('pgsql');
        $writer = DB::connection('p2_peer');
        $this->assertNotSame($this->verifiedBackend($reader, $root), $this->verifiedBackend($writer, $root));
        $this->assertTrue($reader->getSchemaBuilder()->hasTable('replacement_request_slots'), 'Run plain migrations on the verified private cluster first.');

        $fixture = new CancelledSlotFixture;
        CancelledSlotFixture::freezeClock();

        try {
            $fixture->build();
            $this->actingAs($fixture->users['a']);
            $week = CancelledSlotFixture::SOURCE_WEEK;
            $day = CancelledSlotFixture::SOURCE_DAY;
            $session = $fixture->sessions['src'];
            $first = $fixture->createPendingRequest($session, $week + 1, $fixture->altVenue, $week, $day, '14:00:00', 1);
            $second = $fixture->createPendingRequest($session, $week + 2, $fixture->altVenue, $week, $day, '15:00:00', 1);
            $timetable = new CohortTimetable;

            $before = $timetable->myDayClasses($week, $day);
            $this->assertTrue($before['ok']);
            $this->assertCount(1, $before['classes']);
            $this->assertEqualsCanonicalizing([$first->id, $second->id], array_column($before['makeUps'], 'requestId'));
            $vanishing = $before['makeUps'][0]['requestId'] === $first->id ? $first : $second;

            $deleted = false;
            $readSlotsAfterDelete = false;
            $reader->listen(function (QueryExecuted $query) use ($reader, $writer, $vanishing, &$deleted, &$readSlotsAfterDelete): void {
                if ($query->connection !== $reader) {
                    return;
                }

                if (! $deleted && str_contains($query->sql, 'from "replacement_requests"') && str_contains($query->sql, 'as "cells_count"')) {
                    // The reader already has the request rows; the relation query has not run yet.
                    $writer->transaction(function () use ($writer, $vanishing): void {
                        $this->assertSame(1, $writer->table('replacement_requests')->where('id', $vanishing->id)->delete());
                        $this->assertSame(1, $writer->table('time_slots')->where('id', $vanishing->replacement_time_slot_id)->delete());
                    });
                    $deleted = true;
                } elseif ($deleted && str_starts_with($query->sql, 'select * from "time_slots"')) {
                    $readSlotsAfterDelete = true;
                }
            });

            $after = $timetable->myDayClasses($week, $day);
            $this->assertTrue($deleted && $readSlotsAfterDelete, 'The deletion must commit between the request SELECT and the slot SELECT.');
            $before['makeUps'] = [$before['makeUps'][1]];
            $this->assertSame($before, $after);
        } finally {
            try {
                $fixture->cleanup();
            } finally {
                CancelledSlotFixture::unfreezeClock();
            }
        }
    }

    private function verifiedBackend(Connection $connection, string $root): int
    {
        $this->assertSame(0, $connection->transactionLevel(), 'Fixtures must be visible on both connections.');
        $target = $connection->selectOne(<<<'SQL'
            SELECT current_database() AS db, current_setting('port') AS port,
                   current_setting('unix_socket_directories') AS socket_dir,
                   current_setting('data_directory') AS data_dir,
                   current_setting('listen_addresses') AS listen_addresses,
                   current_setting('transaction_isolation') AS isolation,
                   inet_server_addr() AS tcp_address, pg_backend_pid() AS pid
            SQL);

        $this->assertSame('class_replacement_testing', $target->db);
        $this->assertSame('55488', $target->port);
        $this->assertSame($root.'/socket', $target->socket_dir);
        $this->assertSame($root.'/data', $target->data_dir);
        $this->assertSame('', $target->listen_addresses);
        $this->assertSame('read committed', $target->isolation);
        $this->assertNull($target->tcp_address);

        return $target->pid;
    }
}
