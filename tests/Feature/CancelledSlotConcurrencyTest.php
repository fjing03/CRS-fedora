<?php

namespace Tests\Feature;

use App\Actions\ApproveRequest;
use App\Actions\CancelClassBatch;
use App\Actions\SubmitReplacementRequest;
use App\Models\AuditLog;
use App\Models\ClassException;
use App\Models\ClassSession;
use App\Models\Notification;
use App\Models\ReplacementRequest;
use App\Models\ReplacementRequestSlot;
use App\Models\TimeSlot;
use App\Models\User;
use App\Services\OccurrenceWriteLock;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Process\Process;
use Tests\Support\CancelledSlotFixture;
use Tests\TestCase;
use Throwable;

/**
 * SDD cancelled-slot-reuse, tasks A10 + A10b (design D4, spec S12 + S08) —
 * the two-connection race harness and the source interlocks.
 *
 * What is REAL here, not mocked or sequential:
 *   · the race workers are independent PHP processes, each with its own
 *     PostgreSQL backend (Symfony Process, array command, no shell strings),
 *     each pointed EXPLICITLY at `class_replacement_testing` and guarded by
 *     `current_database()` BEFORE any write;
 *   · the parent's fixtures are COMMITTED (no RefreshDatabase, no
 *     DatabaseMigrations — the class_cancelled audit rollback pitfall, and a
 *     framework wrapping transaction would make the rows invisible to the
 *     children);
 *   · both workers pause inside the real submit at a `created` barrier, then
 *     the actual OCC full-span row locks decide the race;
 *   · the interlocks use a second real backend (`lockpeer`) holding the
 *     advisory key, so refusals are observed cross-connection, never through
 *     same-backend re-entrancy.
 *
 * Cleanup is identity-scoped: the parent deletes ONLY the rows it registered
 * (`CancelledSlotFixture::cleanup()`), after the workers finish, bounded by
 * process timeouts and a finally. No migrate:fresh, no reseed, no bulk wipe,
 * no scheduler change; the frozen clock is reset on every exit path.
 *
 * PREREQUISITE: the migrated `class_replacement_testing` schema (the main
 * agent may run the testing migrate first) — this file refuses to run on any
 * other database and fails loudly if the schema is missing.
 */
final class CancelledSlotConcurrencyTest extends TestCase
{
    /** Bounded processes: every worker is stopped by its timeout or in the finally. */
    private const WORKER_TIMEOUT_SECONDS = 120;

    private CancelledSlotFixture $world;

    protected function setUp(): void
    {
        parent::setUp();

        // THE GUARD — before any write: this file may never touch anything
        // but the disposable testing database. (phpunit.xml already pins
        // DB_DATABASE here; the child processes get the same pin explicitly.)
        $row = DB::selectOne('SELECT current_database() AS db');
        $currentDatabase = (is_object($row) && isset($row->db)) ? (string) $row->db : '';

        self::assertSame(
            'class_replacement_testing',
            $currentDatabase,
            'fail-closed: the two-connection race harness may only run on class_replacement_testing',
        );

        // The committed-fixture harness needs the migrated testing schema —
        // it must NOT run migrations itself (see the class docblock).
        foreach (['semesters', 'faculties', 'departments', 'programmes', 'cohorts',
            'venues', 'modules', 'users', 'lecturers', 'class_sessions', 'time_slots',
            'replacement_requests', 'replacement_request_slots', 'audit_logs',
            'class_exceptions', 'holidays', 'notifications'] as $table) {
            self::assertTrue(
                Schema::hasTable($table),
                "the testing schema is missing [{$table}] — run the testing migrate first ".
                '(DB_DATABASE=class_replacement_testing php artisan migrate)',
            );
        }

        CancelledSlotFixture::freezeClock();

        // A random suffix: the shared testing database may hold earlier rows,
        // so every unique code this run creates is unique BY CONSTRUCTION.
        $this->world = new CancelledSlotFixture('-'.strtoupper(bin2hex(random_bytes(3))));
        $this->world->build();

        // the independent second pgsql backend (same database → same advisory
        // lock space, its own session → real cross-connection contention)
        config(['database.connections.lockpeer' => config('database.connections.pgsql')]);
    }

    protected function tearDown(): void
    {
        try {
            CancelledSlotFixture::unfreezeClock();

            try {
                DB::connection('lockpeer')->statement('SELECT pg_advisory_unlock_all()');
            } catch (Throwable) {
                // the peer may never have connected
            }

            try {
                DB::connection('lockpeer')->disconnect();
            } catch (Throwable) {
                // n/a
            }

            try {
                DB::statement('SELECT pg_advisory_unlock_all()');
            } catch (Throwable) {
                // n/a
            }

            $this->world->cleanup();
        } finally {
            parent::tearDown();
        }
    }

    // ── A10: the deterministic two-connection race (S12) ─────────────────

    public function test_two_eligible_sources_race_for_the_released_span_and_exactly_one_wins(): void
    {
        $world = $this->world;

        $world->cancelSource();
        $this->assertReleasedCells();

        $cell1 = $world->slots['released'][0];
        $cell2 = $world->slots['released'][1];

        $results = $this->race([
            ['role' => 'a', 'peer' => 'b', 'user' => $world->users['b'],
                'session' => $world->sessions['b1'], 'anchor' => $cell1, 'span' => 2],
            ['role' => 'b', 'peer' => 'a', 'user' => $world->users['c'],
                'session' => $world->sessions['c1'], 'anchor' => $cell1, 'span' => 2],
        ]);

        $winners = array_filter($results, fn (array $result): bool => (bool) ($result['ok'] ?? false));
        $this->assertCount(1, $winners, 'exactly one submit may win the released span: '.json_encode($results));

        $winnerRole = (string) array_key_first($winners);
        $loserRole = $winnerRole === 'a' ? 'b' : 'a';

        $win = $results[$winnerRole];
        $lose = $results[$loserRole];

        $winnerSession = $winnerRole === 'a' ? $world->sessions['b1'] : $world->sessions['c1'];
        $loserSession = $loserRole === 'a' ? $world->sessions['b1'] : $world->sessions['c1'];
        $loserUser = $loserRole === 'a' ? $world->users['b'] : $world->users['c'];

        $this->assertNull($lose['request_id'] ?? null, 'the loser books nothing');

        $winnerRequest = ReplacementRequest::query()->find((int) $win['request_id']);
        $this->assertNotNull($winnerRequest, 'the winner holds a real request row');
        $this->assertSame('pending', (string) $winnerRequest->status);
        $this->assertSame((int) $winnerSession->id, (int) $winnerRequest->class_session_id);
        $this->assertSame(2, (int) $winnerRequest->replacement_span);

        // no stranded links or partial cells: both released cells are
        // live-linked by the winner's junction receipt and nothing else
        $junctions = ReplacementRequestSlot::query()
            ->whereIn('time_slot_id', [(int) $cell1->id, (int) $cell2->id])
            ->pluck('replacement_request_id')
            ->map(fn ($id): int => (int) $id);
        $this->assertCount(2, $junctions, 'both cells carry exactly one live link each');
        $this->assertTrue($junctions->every(fn (int $id): bool => $id === (int) $winnerRequest->id));

        foreach ([$cell1, $cell2] as $cell) {
            $cell->refresh();
            $this->assertSame('pending', (string) $cell->status, 'the full span is reserved by the winner');
            $this->assertSame(3, (int) $cell->version, 'released (2) → pending (3)');
        }

        // the loser is compensated away — no stranded request row (D5) …
        $this->assertSame(0, ReplacementRequest::query()
            ->where('class_session_id', (int) $loserSession->id)
            ->count(), 'the loser request row is gone');

        // … and its surviving OCC conflict audit (FR 4.12, request id pre-nulled)
        $this->assertSame(1, AuditLog::query()
            ->where('user_id', (int) $loserUser->id)
            ->where('action', 'submitted')
            ->where('occ_validation_result', 'conflict')
            ->whereNull('replacement_request_id')
            ->count(), 'the loser retains its normal OCC conflict audit');

        $this->assertSame(2, AuditLog::query()
            ->where('replacement_request_id', (int) $winnerRequest->id)
            ->where('occ_validation_result', 'success')
            ->count(), 'the winner full-span reservation is audited');

        $this->assertSame(1, ReplacementRequest::query()
            ->whereIn('class_session_id', [(int) $world->sessions['b1']->id, (int) $world->sessions['c1']->id])
            ->whereIn('status', ['pending', 'approved'])
            ->count(), 'exactly one live request exists across the two racing sources');

        // the workers' backends died with their processes — the source keys
        // are free again (no orphan process, lock or backend)
        $lock = app(OccurrenceWriteLock::class);
        $this->assertTrue($lock->trySessionLock((int) $world->sessions['b1']->id), 'the racing worker left no source advisory lock');
        $this->assertTrue($lock->releaseSessionLock((int) $world->sessions['b1']->id));
    }

    // ── A10b: the same-source interlocks ─────────────────────────────────

    /**
     * Cancel vs in-flight submit: while the peer backend holds the SOURCE
     * session advisory key (what a submit holds for its whole run), the
     * cancellation must refuse BEFORE releasing anything — then complete
     * cleanly once the key is free, leaving no advisory lock behind.
     */
    public function test_a_cancel_refuses_while_a_peer_submit_holds_the_source_key_then_completes(): void
    {
        $world = $this->world;
        $src = $world->sessions['src'];
        $lock = app(OccurrenceWriteLock::class);

        $this->assertTrue($lock->trySessionLock((int) $src->id, 'lockpeer'), 'setup: the peer backend takes the source key');

        try {
            $result = (new CancelClassBatch)(
                $world->users['a'],
                [['session' => $src, 'week' => CancelledSlotFixture::SOURCE_WEEK]],
                CancelledSlotFixture::SOURCE_REASON,
            );

            $this->assertFalse($result['ok'], 'the cancellation must refuse while the submit side holds the source key');
            $this->assertTrue(
                ((string) ($result['refused'] ?? '')) !== '' || ((string) ($result['hint'] ?? '')) !== '',
                'the refusal carries its refresh/retry vocabulary',
            );

            // nothing was written and nothing leaked
            $this->assertSame(0, ClassException::query()
                ->where('class_session_id', (int) $src->id)
                ->where('week_number', CancelledSlotFixture::SOURCE_WEEK)
                ->count());
            $this->assertSame(0, AuditLog::query()
                ->where('user_id', (int) $world->users['a']->id)
                ->where('action', 'class_cancelled')
                ->count());
            $this->assertSame(0, Notification::query()->where('user_id', (int) $world->users['a']->id)->count(),
                'a rolled-back batch sends no notifications');

            foreach ($world->slots['released'] as $cell) {
                $cell->refresh();
                $this->assertSame('occupied', (string) $cell->status);
                $this->assertSame((int) $src->id, (int) $cell->class_session_id);
                $this->assertSame(1, (int) $cell->version);
            }
        } finally {
            $this->assertTrue($lock->releaseSessionLock((int) $src->id, 'lockpeer'));
        }

        // the key is free — the cancellation completes for real
        $result = (new CancelClassBatch)(
            $world->users['a'],
            [['session' => $src, 'week' => CancelledSlotFixture::SOURCE_WEEK]],
            CancelledSlotFixture::SOURCE_REASON,
        );

        $this->assertTrue($result['ok'], 'the cancellation completes once the key is released');
        $this->assertReleasedCells();

        // the completed action leaves no source advisory lock behind: the
        // PEER (a different backend) can take the key again
        $this->assertTrue($lock->trySessionLock((int) $src->id, 'lockpeer'), 'no source advisory lock leaked');
        $this->assertTrue($lock->releaseSessionLock((int) $src->id, 'lockpeer'));
    }

    /**
     * Approve vs in-flight submit: the same interlock on the approval side —
     * refuse while the peer holds the source key, never leave the request on
     * an incorrectly released range, then complete cleanly.
     */
    public function test_an_approval_refuses_while_a_peer_submit_holds_the_source_key_then_completes(): void
    {
        $world = $this->world;
        $src = $world->sessions['src'];
        $lock = app(OccurrenceWriteLock::class);

        // a real pending full-span make-up on the source occurrence
        $request = $world->createPendingRequest($src, CancelledSlotFixture::SOURCE_WEEK,
            $world->sourceVenue, CancelledSlotFixture::PIN_WEEK, CancelledSlotFixture::SOURCE_DAY,
            '10:00:00', 2);
        $landingCells = ReplacementRequestSlot::query()
            ->where('replacement_request_id', (int) $request->id)
            ->pluck('time_slot_id')
            ->map(fn ($id): int => (int) $id)
            ->all();
        $this->assertCount(2, $landingCells);

        $this->assertTrue($lock->trySessionLock((int) $src->id, 'lockpeer'));

        try {
            $result = app(ApproveRequest::class)($world->users['pl'], (int) $request->id);

            $this->assertFalse($result['ok'], 'the approval must refuse while the source key is held');

            $request->refresh();
            $this->assertSame('pending', (string) $request->status, 'the refused approval decides nothing');
            $this->assertSame(0, AuditLog::query()
                ->where('replacement_request_id', (int) $request->id)
                ->where('action', 'approved')
                ->count());

            foreach ($landingCells as $slotId) {
                $cell = TimeSlot::find($slotId);
                $this->assertSame('pending', (string) $cell->status, 'the landing range was not released');
            }

            foreach ($world->slots['released'] as $cell) {
                $cell->refresh();
                $this->assertSame('occupied', (string) $cell->status, 'the original range was not released');
                $this->assertSame(1, (int) $cell->version);
            }
        } finally {
            $this->assertTrue($lock->releaseSessionLock((int) $src->id, 'lockpeer'));
        }

        $result = app(ApproveRequest::class)($world->users['pl'], (int) $request->id);

        $this->assertTrue($result['ok'], 'the approval completes once the key is released');
        $this->assertApprovedLandingAndReleasedOriginal($world, $request, $landingCells);

        $this->assertTrue($lock->trySessionLock((int) $src->id, 'lockpeer'), 'no source advisory lock leaked');
        $this->assertTrue($lock->releaseSessionLock((int) $src->id, 'lockpeer'));
    }

    /**
     * Submit vs in-flight cancellation: the peer holds the TRANSACTION-level
     * key inside its own real transaction (what a cancellation holds), the
     * submit refuses with the frozen contention shape BEFORE creating any
     * row, then completes cleanly after the peer rolls back.
     */
    public function test_a_submit_refuses_while_a_peer_cancellation_holds_the_source_key_then_completes(): void
    {
        $world = $this->world;
        $src = $world->sessions['src'];
        $lock = app(OccurrenceWriteLock::class);

        // the conflict facts that make the source's submit eligible: the pin
        // at week 2, and the week-5 exclusion that keeps the source's own
        // recurring class out of the lecturer's busy vector at the landing week
        ClassException::create([
            'class_session_id' => (int) $src->id,
            'week_number' => CancelledSlotFixture::SOURCE_WEEK,
            'reason' => 'official_event',
        ]);
        ClassException::create([
            'class_session_id' => (int) $src->id,
            'week_number' => CancelledSlotFixture::PIN_WEEK,
            'reason' => 'official_event',
        ]);

        $cells = $world->createCells($world->sourceVenue, CancelledSlotFixture::PIN_WEEK,
            CancelledSlotFixture::SOURCE_DAY, '10:00:00', 2, 'available', null);

        $peer = DB::connection('lockpeer');
        $peer->beginTransaction();

        try {
            $lock->lockWithinTransaction([(int) $src->id], 'lockpeer');

            $result = app(SubmitReplacementRequest::class)(
                $world->users['a'],
                $src,
                CancelledSlotFixture::SOURCE_WEEK,
                (int) $cells[0]->id,
                null,
                2,
            );

            $this->assertFalse($result['ok'], 'the submit must refuse while the cancellation side holds the source key');
            $this->assertSame('slot_not_available', $result['conflict_reason'], 'contention maps onto the existing refusal vocabulary');
            $this->assertNull($result['request_id']);
            $this->assertSame(0, ReplacementRequest::query()->where('proposer_id', (int) $world->users['a']->id)->count(),
                'contention refuses BEFORE any request write');
            $this->assertSame(0, AuditLog::query()->where('user_id', (int) $world->users['a']->id)->count());

            foreach ($cells as $cell) {
                $cell->refresh();
                $this->assertSame('available', (string) $cell->status);
                $this->assertSame(1, (int) $cell->version);
            }
        } finally {
            $peer->rollBack();
        }

        $result = app(SubmitReplacementRequest::class)(
            $world->users['a'],
            $src,
            CancelledSlotFixture::SOURCE_WEEK,
            (int) $cells[0]->id,
            null,
            2,
        );

        $this->assertTrue($result['ok'], 'the submit completes once the peer rolls back');
        $this->assertNotNull($result['request_id']);

        // the harness's own request: registered so the identity-scoped
        // cleanup covers its junctions and its success audits by request id
        $this->world->requestIds[] = (int) $result['request_id'];

        $request = ReplacementRequest::query()->findOrFail((int) $result['request_id']);
        $this->assertSame('pending', (string) $request->status);
        $this->assertSame(2, (int) $request->replacement_span);
        $this->assertSame(2, ReplacementRequestSlot::query()
            ->where('replacement_request_id', (int) $request->id)->count());

        foreach ($cells as $cell) {
            $cell->refresh();
            $this->assertSame('pending', (string) $cell->status);
            $this->assertSame(2, (int) $cell->version, 'available (1) → pending (2)');
        }
    }

    // ── helpers ──────────────────────────────────────────────────────────

    /**
     * What the parent's committed cancellation must leave behind BEFORE the
     * workers launch. A failure here is a loud early signal that the
     * cancellation integration (A05/A06) is not in place — the race cannot
     * start on un-released cells.
     */
    private function assertReleasedCells(): void
    {
        $world = $this->world;
        $src = $world->sessions['src'];

        foreach ($world->slots['released'] as $cell) {
            $cell->refresh();
            $this->assertSame('available', (string) $cell->status,
                'the cancelled original cells must be released before the race');
            $this->assertNull($cell->class_session_id);
            $this->assertSame(2, (int) $cell->version);
        }

        $this->assertSame(1, ClassException::query()
            ->where('class_session_id', (int) $src->id)
            ->where('week_number', CancelledSlotFixture::SOURCE_WEEK)
            ->count());
        $this->assertSame(1, AuditLog::query()
            ->where('action', 'class_cancelled')
            ->where('details->class_session_id', (string) $src->id)
            ->where('details->week_number', (string) CancelledSlotFixture::SOURCE_WEEK)
            ->count());
    }

    /**
     * The completed approval: the landing is occupied with its junctions
     * intact, the original occurrence is released (exception stamped once),
     * and the source key is free.
     *
     * @param  list<int>  $landingCells
     */
    private function assertApprovedLandingAndReleasedOriginal(CancelledSlotFixture $world, ReplacementRequest $request, array $landingCells): void
    {
        $request->refresh();
        $this->assertSame('approved', (string) $request->status);
        $this->assertSame((int) $world->users['pl']->id, (int) $request->approver_id);

        $this->assertSame(count($landingCells), ReplacementRequestSlot::query()
            ->where('replacement_request_id', (int) $request->id)
            ->count(), 'the approval keeps its landing junctions');

        foreach ($landingCells as $slotId) {
            $cell = TimeSlot::find($slotId);
            $this->assertSame('occupied', (string) $cell->status, 'the approval never releases its own landing');
        }

        foreach ($world->slots['released'] as $cell) {
            $cell->refresh();
            $this->assertSame('available', (string) $cell->status, 'the approval releases the original occurrence');
            $this->assertNull($cell->class_session_id);
            $this->assertSame(2, (int) $cell->version);
        }

        $this->assertSame(1, ClassException::query()
            ->where('class_session_id', (int) $world->sessions['src']->id)
            ->where('week_number', CancelledSlotFixture::SOURCE_WEEK)
            ->count(), 'the original exception is stamped exactly once');
    }

    /**
     * Launch the two race workers as independent processes, wait for both
     * (bounded), and return their business results keyed by role. The barrier
     * directory and any still-running child are always cleaned up.
     *
     * @param  array<int, array{role: string, peer: string, user: User, session: ClassSession, anchor: TimeSlot, span: int}>  $participants
     * @return array<string, array<string, mixed>>
     */
    private function race(array $participants): array
    {
        $barrier = sys_get_temp_dir().'/cancelled-slot-race-'.bin2hex(random_bytes(6));

        if (! mkdir($barrier, 0700, true) && ! is_dir($barrier)) {
            self::fail('could not create the barrier directory');
        }

        $processes = [];

        try {
            foreach ($participants as $participant) {
                $payloadPath = $barrier.'/payload-'.$participant['role'].'.json';

                file_put_contents($payloadPath, json_encode([
                    'db' => 'class_replacement_testing',
                    'barrier' => $barrier,
                    'role' => $participant['role'],
                    'peer' => $participant['peer'],
                    'user_id' => (int) $participant['user']->id,
                    'session_id' => (int) $participant['session']->id,
                    'pin_week' => CancelledSlotFixture::PIN_WEEK,
                    'anchor_slot_id' => (int) $participant['anchor']->id,
                    'span' => (int) $participant['span'],
                    'test_now' => CancelledSlotFixture::FROZEN_NOW,
                ]));

                $process = new Process(
                    [PHP_BINARY, base_path('tests/helpers/cancelled-slot-concurrency-worker.php'), $payloadPath],
                    base_path(),
                    $this->workerEnv(),
                );
                $process->setTimeout(self::WORKER_TIMEOUT_SECONDS);
                $process->start();
                $processes[$participant['role']] = $process;
            }

            foreach ($processes as $process) {
                $process->wait();
            }

            $results = [];

            foreach ($processes as $role => $process) {
                $resultPath = $barrier.'/result-'.$role.'.json';

                if (! is_file($resultPath)) {
                    $errorPath = $barrier.'/error-'.$role.'.json';
                    $error = is_file($errorPath) ? (string) file_get_contents($errorPath) : '(no error file)';

                    self::fail("worker [{$role}] produced no result (exit ".$process->getExitCode().'): '
                        .$error."\n".$process->getErrorOutput());
                }

                $decoded = json_decode((string) file_get_contents($resultPath), true);
                self::assertIsArray($decoded, "worker [{$role}] wrote an unreadable result");

                // the worker-created request is an own fixture: register it so
                // the tearDown cleanup covers its fan-out rows by request id
                if (isset($decoded['request_id']) && $decoded['request_id'] !== null) {
                    $this->world->requestIds[] = (int) $decoded['request_id'];
                }

                $results[$role] = $decoded;
            }

            return $results;
        } finally {
            foreach ($processes as $process) {
                if ($process->isRunning()) {
                    $process->stop(0);
                }
            }

            foreach (glob($barrier.'/*') ?: [] as $file) {
                @unlink($file);
            }

            @rmdir($barrier);
        }
    }

    /**
     * The explicit child environment: the testing database BY NAME, the
     * phpunit-like mailers/queue/cache. No hidden demo fallback, nothing
     * inherited blindly, no shell string anywhere.
     *
     * @return array<string, string>
     */
    private function workerEnv(): array
    {
        $conn = config('database.connections.pgsql');

        return [
            'APP_ENV' => 'testing',
            'DB_CONNECTION' => 'pgsql',
            'DB_HOST' => (string) ($conn['host'] ?? '127.0.0.1'),
            'DB_PORT' => (string) ($conn['port'] ?? '5432'),
            'DB_DATABASE' => 'class_replacement_testing',
            'DB_USERNAME' => (string) ($conn['username'] ?? ''),
            'DB_PASSWORD' => (string) ($conn['password'] ?? ''),
            'MAIL_MAILER' => 'array',
            'QUEUE_CONNECTION' => 'sync',
            'CACHE_STORE' => 'array',
            'SESSION_DRIVER' => 'array',
        ];
    }
}
