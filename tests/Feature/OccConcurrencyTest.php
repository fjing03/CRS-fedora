<?php

namespace Tests\Feature;

use App\Actions\SubmitReplacementRequest;
use App\Models\AuditLog;
use App\Models\ClassSession;
use App\Models\ReplacementRequest;
use App\Models\TimeSlot;
use App\Models\User;
use App\Services\MatrixIntersectionEngine;
use App\Services\OCCValidator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

/**
 * Design testing-strategy row "OccConcurrencyTest": exactly-one-winner via two
 * connections. Connection "hold" grabs the anchor row lock and flips the slot
 * (uncommitted); the loser's action runs on the default connection — its
 * pre-check reads the pre-flip snapshot (available), but the validator blocks
 * on the row lock until the session lock_timeout fires. The action MUST map
 * that abort to a conflict result with D5 compensation (FR 4.10 alert, no 500):
 * loser request row removed, no orphan audit row, slot untouched after rollback,
 * then the winner path succeeds cleanly (exactly one reservation).
 *
 * DatabaseMigrations (no wrapping transaction) so the second connection sees
 * the seeded, committed state. The winner-side version check itself is atomic
 * (raw UPDATE … WHERE version = ?) — proven in OCCValidatorTest.
 */
final class OccConcurrencyTest extends TestCase
{
    use DatabaseMigrations;

    private const LOCK_TIMEOUT_MS = 300;

    protected function setUp(): void
    {
        parent::setUp();

        DB::statement("SELECT setval(pg_get_serial_sequence('class_sessions', 'id'), 1, false)");

        $this->seed();

        // Sequence reset (non-transactional) — same rationale as ReplacementFlowTest.
        foreach (['replacement_requests', 'class_exceptions', 'audit_logs'] as $table) {
            DB::statement("SELECT setval(pg_get_serial_sequence('{$table}', 'id'), 1, false)");
        }
    }

    protected function tearDown(): void
    {
        // hold connection always releases (failure paths too — the stale
        // idle-in-transaction locks would deadlock the next refresh).
        try {
            DB::connection('hold')->statement('ROLLBACK');
            DB::connection('hold')->disconnect();
        } catch (Throwable $e) {
            // n/a when the test never opened it
        }

        DB::purge(); // resets the session lock_timeout set for this test

        parent::tearDown();
    }

    public function test_blocked_loser_is_compensated_and_winner_is_unique(): void
    {
        $engine = new MatrixIntersectionEngine;
        $action = new SubmitReplacementRequest(new OCCValidator, $engine);

        [$winner, $winnerSession, $loser, $loserSession, $anchor] = $this->racePair($engine);
        $versionBefore = (int) $anchor->version;

        // The "hold" connection grabs the anchor row lock and flips the slot,
        // uncommitted — invisible to the loser's pre-check, blocking its validator.
        config(['database.connections.hold' => config('database.connections.pgsql')]);
        DB::connection('hold')->statement('BEGIN');
        DB::connection('hold')->statement(
            'UPDATE time_slots SET status = ?, version = version + 1 WHERE id = ?',
            ['pending', $anchor->id]
        );

        DB::statement('SET lock_timeout = '.self::LOCK_TIMEOUT_MS);

        try {
            $result = $action($loser, $loserSession, (int) $anchor->week_number, (int) $anchor->id, null);

            $this->assertFalse($result['ok'], 'FR 4.10: the blocked loser must get a conflict alert, not a 500');
            $this->assertSame('reserve_interrupted', $result['conflict_reason']);
            $this->assertSame(0, ReplacementRequest::query()->where('proposer_id', $loser->id)->count(), 'D5: loser row removed');

            /* DESIGNED CONTRACT CHANGE (full-block-reservation, design D3 —
               round-1-verify 🔴2): the two-phase validator's peri-flip catch
               writes the single conflict audit row AFTER the rollback (id
               pre-nulled so postRollbackAudit survives and D5's UPDATE no-ops).
               Here the lock-timeout abort fires INSIDE the txn at the lock step
               — the exception propagates BEFORE any flip, so the post-rollback
               row is exactly one, with the FR 4.12 survival + the forensic
               token + the failing slot's facts. */
            $lateAudit = AuditLog::query()
                ->where('occ_validation_result', 'conflict')
                ->where('details->conflict_reason', 'concurrent_reserve_conflict')
                ->whereNull('replacement_request_id')
                ->first();
            $this->assertNotNull($lateAudit, 'the post-rollback audit row survives (FR 4.12)');
            $this->assertSame($anchor->id, $lateAudit->time_slot_id);

            DB::connection('hold')->statement('ROLLBACK');
        } finally {
            // guards the failure path: never leave the hold txn holding locks —
            // the next test's migrate:fresh would deadlock (observed live).
            try {
                DB::connection('hold')->statement('ROLLBACK');
            } catch (Throwable $e) {
                // txn may already be closed (the happy path rolled back above)
            }
            DB::connection('hold')->disconnect();
        }

        $anchor->refresh();
        $this->assertSame('available', $anchor->status, 'Rollback of the hold leaves the slot available');
        $this->assertSame($versionBefore, (int) $anchor->version, 'Rollback restores the pre-hold version');

        // Winner path then succeeds cleanly — exactly one reservation exists.
        $win = $action($winner, $winnerSession, (int) $anchor->week_number, (int) $anchor->id, null);

        $this->assertTrue($win['ok'], 'Winner submit must succeed after the hold releases');
        $anchor->refresh();
        $this->assertSame('pending', $anchor->status);
        $this->assertSame($versionBefore + 1, (int) $anchor->version);
        $this->assertSame(1, ReplacementRequest::query()->where('status', 'pending')->count());

        $successAudit = AuditLog::query()
            ->where('occ_validation_result', 'success')
            ->where('replacement_request_id', $win['request_id'])
            ->exists();
        $this->assertTrue($successAudit, 'Winner outcome audited (FR 4.12)');
    }

    /**
     * Two distinct lecturers, each with an owned session, and ONE anchor slot
     * where BOTH pass the engine pre-check — the deterministic race setup.
     * Anchors are collected from the winner's free (week, day) pairs at the
     * winner session's own venue, then matched against the loser's sessions.
     *
     * @return array{0: User, 1: ClassSession, 2: User, 3: ClassSession, 4: TimeSlot}
     */
    private function racePair(MatrixIntersectionEngine $engine): array
    {
        $users = User::query()
            ->whereIn('id', ClassSession::query()->select('lecturer_id'))
            ->where('role', 'lecturer')
            ->orderBy('id')
            ->get();
        $winner = $users->get(0) ?? throw new RuntimeException('no lecturers seeded');
        $loser = $users->get(1) ?? throw new RuntimeException('need two lecturers seeded');

        $loserSessions = ClassSession::query()->where('lecturer_id', $loser->id)->with('cohorts')->orderBy('id')->get();
        $winnerPairs = $this->busyPairs(
            TimeSlot::query()->whereIn('class_session_id', ClassSession::query()->where('lecturer_id', $winner->id)->select('id')),
        );

        foreach (ClassSession::query()->where('lecturer_id', $winner->id)->with('cohorts')->orderBy('id')->get() as $winnerSession) {
            $winnerCohorts = $winnerSession->cohorts->pluck('id')->all();
            $anchors = [];

            foreach ($this->freePairs($winnerPairs) as [$week, $day]) {
                foreach (TimeSlot::query()
                    ->where('status', 'available')
                    ->where('venue_id', $winnerSession->venue_id)
                    ->where('week_number', $week)
                    ->where('day_of_week', $day)
                    ->orderBy('id')
                    ->get() as $candidate) {
                    if ($engine->validateSlot((int) $candidate->id, (int) $winner->id, $winnerCohorts, $winnerSession->session_type, $week)) {
                        $anchors[] = $candidate;
                    }
                }

                if (count($anchors) >= 40) {
                    break;
                }
            }

            foreach ($anchors as $anchor) {
                foreach ($loserSessions as $loserSession) {
                    if ($engine->validateSlot((int) $anchor->id, (int) $loser->id, $loserSession->cohorts->pluck('id')->all(), $loserSession->session_type, (int) $anchor->week_number)) {
                        return [$winner, $winnerSession, $loser, $loserSession, $anchor];
                    }
                }
            }
        }

        throw new RuntimeException('No deterministic race pair found in the seeded dataset');
    }

    /**
     * @param  Builder<TimeSlot>  $query
     * @return Collection<int, array{week: int, day: int}>
     */
    private function busyPairs($query): Collection
    {
        return $query->select('week_number', 'day_of_week')->distinct()->get()
            ->map(fn (TimeSlot $slot): array => ['week' => (int) $slot->week_number, 'day' => (int) $slot->day_of_week]);
    }

    /**
     * All (week, day) pairs of the semester that are NOT in the given busy set.
     *
     * @param  Collection<int, array{week: int, day: int}>  $busy
     * @return \Generator<int, array{0: int, 1: int}>
     */
    private function freePairs(Collection $busy): \Generator
    {
        for ($week = 1; $week <= 14; $week++) {
            for ($day = 0; $day <= 5; $day++) {
                if (! $busy->contains(fn (array $pair): bool => $pair['week'] === $week && $pair['day'] === $day)) {
                    yield [$week, $day];
                }
            }
        }
    }
}
