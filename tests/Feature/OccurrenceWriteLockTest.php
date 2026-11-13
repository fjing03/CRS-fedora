<?php

namespace Tests\Feature;

use App\Actions\SubmitReplacementRequest;
use App\Exceptions\OccurrenceWriteLockContended;
use App\Models\AuditLog;
use App\Models\ClassException;
use App\Models\ClassSession;
use App\Models\Cohort;
use App\Models\Department;
use App\Models\Faculty;
use App\Models\Lecturer;
use App\Models\Module;
use App\Models\Programme;
use App\Models\ReplacementRequest;
use App\Models\ReplacementRequestSlot;
use App\Models\Semester;
use App\Models\TimeSlot;
use App\Models\User;
use App\Models\Venue;
use App\Services\OccurrenceWriteLock;
use App\Support\LocalTime;
use Illuminate\Database\Connection;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use LogicException;
use RuntimeException;
use Tests\TestCase;
use Throwable;

/**
 * SDD cancelled-slot-reuse, task A04 (design D4 / spec S12) — the
 * `OccurrenceWriteLock` advisory boundary and the `SubmitReplacementRequest`
 * scoped-lock wrapper.
 *
 * Covered here:
 *  1. helper release on success / ordinary refusal / thrown exception;
 *  2. TWO independent pgsql connections (the app connection inside the
 *     RefreshDatabase wrapping transaction + a `lockpeer` connection) showing
 *     session-level vs transaction-level contention on the SAME source id in
 *     BOTH directions, and NO contention on a different source id;
 *  3. the transaction-level lock requires an active transaction;
 *  4. sorted, deduplicated multi-source acquisition and refusal;
 *  5. the submit wrapper: contended refusal BEFORE any request write, ordinary
 *     validation refusal releases, success releases, and the loser path keeps
 *     its untouched no-outer-transaction audit/compensation contract AND
 *     releases the lock.
 *
 * Advisory keys are plain integers — no rows are needed for the pure-lock
 * tests, so they run against arbitrary source ids. The submit tests reuse the
 * SubmitGatesTest-style fixture (frozen clock, valid 30-minute cell).
 *
 * RefreshDatabase targets the disposable `class_replacement_testing` DB only
 * (phpunit.xml); every lock is swept in tearDown.
 */
final class OccurrenceWriteLockTest extends TestCase
{
    use RefreshDatabase;

    private OccurrenceWriteLock $lock;

    private ?User $lecturer = null;

    private ?ClassSession $session = null;

    private ?TimeSlot $cell = null;

    protected function setUp(): void
    {
        parent::setUp();

        // The second, independent pgsql connection — SAME database (same
        // advisory-lock space), NO wrapping transaction, NO shared session.
        config(['database.connections.lockpeer' => config('database.connections.pgsql')]);

        $this->lock = app(OccurrenceWriteLock::class);
    }

    protected function tearDown(): void
    {
        // A failure path must never leave a session lock behind (xact locks of
        // the framework's wrapping transaction die with its rollback).
        try {
            DB::statement('SELECT pg_advisory_unlock_all()');
            DB::connection('lockpeer')->statement('SELECT pg_advisory_unlock_all()');
        } catch (Throwable) {
            // the peer connection may never have connected
        }

        DB::connection('lockpeer')->disconnect();
        Carbon::setTestNow();

        parent::tearDown();
    }

    // ── 1. the session-level run releases on every exit path ─────────────

    public function test_run_releases_on_success(): void
    {
        $value = $this->lock->runWithSessionLock(101, static fn (): string => 'ran');

        $this->assertSame('ran', $value);
        $this->assertTrue($this->peerCanTake(101), 'the session lock must be released after success');
    }

    public function test_run_releases_on_ordinary_refusal(): void
    {
        $result = $this->lock->runWithSessionLock(102, static fn (): array => [
            'ok' => false,
            'conflict_reason' => 'slot_not_available',
        ]);

        $this->assertFalse($result['ok']);
        $this->assertTrue($this->peerCanTake(102), 'an ordinary business refusal must still release the lock');
    }

    public function test_run_releases_on_thrown_exception(): void
    {
        try {
            $this->lock->runWithSessionLock(103, static function (): void {
                throw new RuntimeException('boom');
            });
            $this->fail('the callback exception must propagate out of the run');
        } catch (RuntimeException) {
            // expected — the exception passes through untouched
        }

        $this->assertTrue($this->peerCanTake(103), 'a thrown exception must still release the lock in the finally');
    }

    // ── 2. the session/transaction interlock, both directions ────────────

    public function test_session_lock_refuses_while_a_transaction_lock_holds_the_same_source(): void
    {
        $peer = $this->peer();
        $peer->beginTransaction();

        try {
            $this->assertTrue($this->lock->lockWithinTransaction([201], 'lockpeer'));

            $callbackRan = false;
            try {
                $this->lock->runWithSessionLock(201, static function () use (&$callbackRan) {
                    $callbackRan = true;

                    return 'never';
                });
                $this->fail('same-source contention must refuse the session-level taker');
            } catch (OccurrenceWriteLockContended $exception) {
                $this->assertSame(201, $exception->sourceSessionId);
                $this->assertFalse($callbackRan, 'contention refuses BEFORE any callback work');
            }

            // a DIFFERENT source id in the same pair: no contention
            $this->assertSame('ok', $this->lock->runWithSessionLock(202, static fn (): string => 'ok'));
        } finally {
            $peer->rollBack();
        }
    }

    public function test_transaction_lock_refuses_while_a_session_lock_holds_the_same_source(): void
    {
        $held = $this->lock->runWithSessionLock(301, function (): string {
            $peer = $this->peer();
            $peer->beginTransaction();

            try {
                try {
                    $this->lock->lockWithinTransaction([301], 'lockpeer');
                    $this->fail('same-source contention must refuse the transaction-level taker');
                } catch (OccurrenceWriteLockContended $exception) {
                    $this->assertSame(301, $exception->sourceSessionId);
                }

                // a different source id inside the same peer transaction: free
                $this->assertTrue($this->lock->lockWithinTransaction([302], 'lockpeer'));
            } finally {
                $peer->rollBack();
            }

            return 'held';
        });

        $this->assertSame('held', $held);
    }

    // ── 3. the transaction-level lock requires an active transaction ─────

    public function test_transaction_lock_requires_an_active_transaction(): void
    {
        // the peer connection has NO transaction here — a transaction-scoped
        // lock taken without one would protect nothing, so it must refuse hard
        try {
            $this->lock->lockWithinTransaction([401], 'lockpeer');
            $this->fail('the transaction-level lock must require an active transaction');
        } catch (LogicException $exception) {
            $this->assertStringContainsString('active database transaction', $exception->getMessage());
        }

        // positive control: the default connection runs inside the
        // RefreshDatabase wrapping transaction, so the lock is accepted
        $this->assertTrue($this->lock->lockWithinTransaction([402]));
    }

    // ── 4. sorted, deduplicated multi-source acquisition/refusal ─────────

    public function test_transaction_lock_acquires_sorted_unique_multisource(): void
    {
        $this->assertTrue($this->lock->lockWithinTransaction([505, 502, 505, 503]));

        // every id of the set is held; an id outside it stays free
        $this->assertFalse($this->peerCanTake(502));
        $this->assertFalse($this->peerCanTake(503));
        $this->assertFalse($this->peerCanTake(505));
        $this->assertTrue($this->peerCanTake(504), 'an id outside the set was never touched');
    }

    public function test_transaction_lock_refuses_at_the_first_held_id_in_ascending_order(): void
    {
        // the peer holds TWO ids of the set — the sorted order must stop at
        // the SMALLER contended id, proving the acquisition is ascending
        $this->assertTrue($this->lock->trySessionLock(502, 'lockpeer'));
        $this->assertTrue($this->lock->trySessionLock(505, 'lockpeer'));

        try {
            $this->lock->lockWithinTransaction([505, 502]);
            $this->fail('one held source id must refuse the whole set');
        } catch (OccurrenceWriteLockContended $exception) {
            $this->assertSame(502, $exception->sourceSessionId, 'acquisition must run in ascending id order');
        }
    }

    // ── 5. the submit wrapper (D4, source-keyed, no outer transaction) ───

    public function test_contended_submit_refuses_before_any_write_then_succeeds_once_free(): void
    {
        $this->bookableFixture();

        // the "cancellation side" holds the source lock on the peer connection
        $this->assertTrue($this->lock->trySessionLock((int) $this->session->id, 'lockpeer'));

        $result = app(SubmitReplacementRequest::class)($this->lecturer, $this->session, 5, (int) $this->cell->id);

        $this->assertFalse($result['ok']);
        $this->assertSame('slot_not_available', $result['conflict_reason'], 'contention maps onto the EXISTING refusal vocabulary');
        $this->assertNull($result['request_id']);
        $this->assertSame(0, ReplacementRequest::query()->count(), 'contention refuses BEFORE any request write');

        // NOTE: probing the PEER here would prove nothing — pg session locks
        // are REENTRANT, so the holding session always re-takes its own key
        // (and that probe would even add a recursion count). The real proof
        // that the peer lock is unharmed is that the DEFAULT connection — a
        // different backend session — cannot take the source key.
        $this->assertFalse(
            $this->lock->trySessionLock((int) $this->session->id),
            'the source key must still be held: the default connection is refused while the peer holds it'
        );

        $this->lock->releaseSessionLock((int) $this->session->id, 'lockpeer');

        $win = app(SubmitReplacementRequest::class)($this->lecturer, $this->session, 5, (int) $this->cell->id);

        $this->assertTrue($win['ok'], 'once the peer releases, the wrapper runs the real submit');
        $this->assertTrue($this->peerCanTake((int) $this->session->id), 'a successful submit releases the source lock');
    }

    public function test_ordinary_validation_refusal_releases_the_source_lock(): void
    {
        $this->bookableFixture();

        $result = app(SubmitReplacementRequest::class)($this->lecturer, $this->session, 99, (int) $this->cell->id);

        $this->assertFalse($result['ok']);
        $this->assertSame('invalid_week', $result['conflict_reason']);
        $this->assertSame(0, ReplacementRequest::query()->count());
        $this->assertTrue($this->peerCanTake((int) $this->session->id), 'an ordinary refusal inside the run releases the lock');
    }

    public function test_loser_path_keeps_audit_compensation_and_releases_the_source_lock(): void
    {
        $this->bookableFixture();
        $slot = $this->cell;
        $versionBefore = (int) $slot->version;

        // A real, deterministic OCC peri-flip failure (the SpanReservationTest
        // T15d shape — NOT a peer-UPDATE simulation, which cannot work here:
        // the peer session cannot even see the RefreshDatabase-wrapped row).
        // A phantom junction row is pre-seeded on a DIFFERENT source
        // occurrence/week (week 6 ≠ the submit's week 5), pinned to the anchor
        // slot — the source's week-5 business gates stay clean, but the
        // two-phase's junction INSERT hits the UNIQUE(time_slot_id) violation
        // AFTER the flips, surfacing `reserve_interrupted`.
        $otherSession = ClassSession::create([
            'semester_id' => $this->session->semester_id,
            'module_id' => $this->session->module_id,
            'lecturer_id' => $this->session->lecturer_id,
            'day_of_week' => 1,
            'start_time' => '10:00:00',
            'end_time' => '12:00:00',
            'venue_id' => $this->session->venue_id,
            'session_type' => 'L',
        ]);
        $phantom = ReplacementRequest::create([
            'semester_id' => $this->session->semester_id,
            'proposer_id' => $this->lecturer->id,
            'class_session_id' => $otherSession->id,
            'week_number' => 6,
            'replacement_time_slot_id' => $slot->id,
            'status' => 'pending',
            'submitted_at' => now(),
        ]);
        ReplacementRequestSlot::create([
            'replacement_request_id' => $phantom->id,
            'time_slot_id' => $slot->id,
        ]);

        $result = app(SubmitReplacementRequest::class)($this->lecturer, $this->session, 5, (int) $slot->id);

        $this->assertFalse($result['ok']);
        $this->assertSame('reserve_interrupted', $result['conflict_reason'], 'the peri-flip failure surfaces non-500');

        // the SOURCE loser's request row is compensated away (D5) while the
        // phantom request is retained
        $this->assertSame(0, ReplacementRequest::query()
            ->where('proposer_id', $this->lecturer->id)
            ->where('week_number', 5)
            ->count(), 'D5: the loser request row is deleted');
        $this->assertTrue($phantom->refresh()->exists(), 'the phantom request is retained');

        // the conflict audit row survives the loser path (FR 4.12)
        $lateAudit = AuditLog::query()
            ->where('occ_validation_result', 'conflict')
            ->where('details->conflict_reason', 'concurrent_reserve_conflict')
            ->whereNull('replacement_request_id')
            ->first();
        $this->assertNotNull($lateAudit, 'FR 4.12: the conflict audit row survives the loser path');

        // the two-phase rollback: the anchor slot is NOT left pending
        $slot->refresh();
        $this->assertSame('available', (string) $slot->status, 'the peri-flip rollback leaves the slot available');
        $this->assertSame($versionBefore, (int) $slot->version, 'the rollback restores the version');

        // the source lock is RELEASED after the loser compensation — verified
        // on the DEFAULT connection (the holder's own backend); a peer probe
        // here would be reentrant noise, not a lock-release proof.
        $this->assertTrue($this->lock->trySessionLock((int) $this->session->id), 'the loser path releases the source lock');
        $this->assertTrue($this->lock->releaseSessionLock((int) $this->session->id));
    }

    // ── 6. the D4 release fail-safe: unverifiable release purges ────────

    public function test_unverifiable_release_purges_the_captured_connection(): void
    {
        // A live-but-ABORTED transaction on the peer connection refuses every
        // statement, the release included — yet it still HOLDS the session
        // advisory lock server-side. D4: such an unverifiable release must
        // disconnect the CAPTURED connection (never hand a still-locked
        // backend back for reuse) and must never mask the callback's result.
        $peer = $this->peer();
        $peer->beginTransaction();

        $value = $this->lock->runWithSessionLock(901, static function () use ($peer): string {
            try {
                $peer->statement('SELECT 1/0'); // aborts the peer transaction
            } catch (QueryException) {
                // the abort itself is the setup, not the subject
            }

            return 'survived';
        }, 'lockpeer');

        $this->assertSame('survived', $value, 'the release failure must never mask the callback result');
        $this->assertNull(
            (new \ReflectionObject($peer))->getProperty('pdo')->getValue($peer),
            'an unverifiable release must disconnect the captured connection'
        );

        // the stranded session lock died with the backend: a fresh peer
        // session CAN take the key
        $this->assertTrue($this->peerCanTake(901), 'the stranded lock died with the disconnected backend');
    }

    public function test_callback_exception_surfaces_even_when_the_release_is_unverifiable(): void
    {
        $peer = $this->peer();

        try {
            $this->lock->runWithSessionLock(902, static function () use ($peer): void {
                $peer->beginTransaction();

                try {
                    $peer->statement('SELECT 1/0'); // aborts the peer transaction
                } catch (QueryException) {
                    // the abort itself is the setup, not the subject
                }

                throw new RuntimeException('boom on an aborted release');
            }, 'lockpeer');
            $this->fail('the callback exception must surface even when the release is unverifiable');
        } catch (RuntimeException) {
            // expected — the exception passes through untouched
        }

        $this->assertNull(
            (new \ReflectionObject($peer))->getProperty('pdo')->getValue($peer),
            'the unverifiable release must still purge the connection behind the exception'
        );
    }

    // ── helpers ──────────────────────────────────────────────────────────

    /**
     * The independent second pgsql connection (same database → same
     * advisory-lock space, its own session → real contention).
     */
    private function peer(): Connection
    {
        return DB::connection('lockpeer');
    }

    /**
     * Whether the peer connection can take the session-level lock for a source
     * id (a `true` answer TAKES it — tearDown sweeps every peer lock).
     */
    private function peerCanTake(int $sourceSessionId): bool
    {
        return $this->lock->trySessionLock($sourceSessionId, 'lockpeer');
    }

    /**
     * The SubmitGatesTest-style fixture: a frozen clock (Mon 19 Oct 2026, so
     * week 5 Thursday 22 Oct is the FR 2.17 cutoff), a lecturer-owned class
     * session, and one available 30-minute bookable cell.
     */
    private function bookableFixture(): void
    {
        if ($this->session !== null) {
            return;
        }

        Carbon::setTestNow(Carbon::parse('2026-10-19 09:00', LocalTime::TZ));

        $semester = Semester::create([
            'semester_code' => 'LT001',
            'label' => 'Lock Fixture',
            'start_date' => '2026-09-21', // a MONDAY — week 5 = Mon 19 Oct
            'end_date' => '2026-12-27',
            'week_count' => 14,
        ]);

        $faculty = Faculty::create(['faculty_code' => 'LTF', 'faculty_name' => 'Lock Faculty']);
        $department = Department::create(['dept_code' => 'LTD', 'dept_name' => 'Lock Dept', 'faculty_id' => $faculty->id]);
        $programme = Programme::create(['programme_code' => 'LTP', 'programme_name' => 'Lock Programme', 'faculty_id' => $faculty->id]);

        $cohort = Cohort::create([
            'programme_id' => $programme->id,
            'current_year' => 3,
            'semester' => 1,
            'tutorial_group' => 1,
            'academic_year' => '2026/27',
            'intake' => 'June 2024',
            'student_count' => 20,
        ]);

        $venue = Venue::create([
            'room_code' => 'LT101',
            'capacity' => 40,
            'room_type' => 'tutorial',
            'allowed_session_types' => 'L,T',
        ]);

        $module = Module::create([
            'module_code' => 'LT1001',
            'module_name' => 'Lock Module',
            'allowed_session_types' => 'L,T',
        ]);

        $this->lecturer = User::factory()->create(['role' => 'lecturer', 'name' => 'Lock Lecturer']);
        Lecturer::create([
            'user_id' => $this->lecturer->id,
            'staff_id' => 'LT0001',
            'dept_id' => $department->id,
            'is_pl' => false,
        ]);

        $this->session = ClassSession::create([
            'semester_id' => $semester->id,
            'module_id' => $module->id,
            'lecturer_id' => $this->lecturer->id,
            'day_of_week' => 0,
            'start_time' => '10:00:00',
            'end_time' => '12:00:00',
            'venue_id' => $venue->id,
            'session_type' => 'L',
        ]);
        $this->session->cohorts()->attach([$cohort->id]);

        // the conflicted occurrence that pins week 5 as replaceable
        ClassException::create([
            'class_session_id' => $this->session->id,
            'week_number' => 5,
            'reason' => 'official_event',
        ]);

        $this->cell = TimeSlot::create([
            'semester_id' => $semester->id,
            'class_session_id' => null,
            'week_number' => 5,
            'day_of_week' => 3, // Thu 22 Oct — the FR 2.17 cutoff itself
            'start_time' => '14:00:00',
            'end_time' => '14:30:00',
            'venue_id' => $venue->id,
            'status' => 'available',
            'version' => 1,
        ]);
    }
}
