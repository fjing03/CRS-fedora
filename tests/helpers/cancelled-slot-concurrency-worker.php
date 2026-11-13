<?php

/**
 * SDD cancelled-slot-reuse, task A10 (design D4, spec S12) — one side of the
 * deterministic two-connection race harness.
 *
 * Each worker is an INDEPENDENT PHP process with its OWN PostgreSQL backend,
 * pointed explicitly at `class_replacement_testing` via the environment the
 * parent passes (never a demo fallback). It submits a replacement for its own
 * source class at the released full span, but pauses INSIDE the submit — via
 * a `ReplacementRequest::created` hook — until BOTH workers have committed
 * their request rows, and only then lets the real OCC race run.
 *
 * Why this is deterministic: the submit action has NO outer transaction, so
 * the request INSERT autocommits before the `created` event fires. Both
 * workers therefore pass every pre-check while the cells are still available,
 * and the race is decided by the OCC full-span row locks themselves — not by
 * process scheduling, not by sequential mocks.
 *
 * Usage (launched by tests/Feature/CancelledSlotConcurrencyTest.php):
 *   php tests/helpers/cancelled-slot-concurrency-worker.php <payload.json>
 *
 * The payload is a JSON file (never a shell string) with:
 *   db, barrier, role, peer, user_id, session_id, pin_week, anchor_slot_id,
 *   span, test_now.
 *
 * Exit codes: 0 = the submit ran (business result in `result-<role>.json`),
 * 2 = the worker crashed (details in `error-<role>.json`), 3 = wrong database.
 * The worker owns no fixture rows: the parent creates and cleans everything.
 */

use App\Actions\SubmitReplacementRequest;
use App\Models\ClassSession;
use App\Models\ReplacementRequest;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

define('CRS_WORKER_BARRIER_SECONDS', 15.0);

$payloadPath = $argv[1] ?? null;

if ($payloadPath === null || ! is_file($payloadPath)) {
    fwrite(STDERR, "usage: php cancelled-slot-concurrency-worker.php <payload.json>\n");

    exit(2);
}

$payload = json_decode((string) file_get_contents($payloadPath), true);

if (! is_array($payload)
    || ! isset($payload['db'], $payload['barrier'], $payload['role'], $payload['peer'])
    || ! isset($payload['user_id'], $payload['session_id'], $payload['pin_week'])
    || ! isset($payload['anchor_slot_id'], $payload['span'], $payload['test_now'])) {
    fwrite(STDERR, "payload is incomplete\n");

    exit(2);
}

/** Write one result/error file and return an exit code — never throws. */
function crsWorkerWrite(string $barrier, string $role, string $kind, array $data): void
{
    @file_put_contents($barrier.'/'.$kind.'-'.$role.'.json', json_encode($data));
}

// THE PRE-BOOTSTRAP GUARD — fail closed BEFORE vendor/autoload, the app or
// any database connection: the payload must pin the disposable testing
// database by its literal name AND the process env must already carry the
// same literal DB_DATABASE. No --env fallback, nothing inherited blindly; a
// stale cached config or a demo .env cannot rescue a run that arrives
// without the explicit pin.
$payloadDb = (string) $payload['db'];
$envDb = getenv('DB_DATABASE');

if ($payloadDb !== 'class_replacement_testing'
    || $envDb === false || (string) $envDb !== 'class_replacement_testing') {
    crsWorkerWrite((string) $payload['barrier'], (string) $payload['role'], 'error', [
        'exception' => 'wrong_database',
        'message' => 'pre-bootstrap guard refused: payload db = '.$payloadDb
            .', DB_DATABASE = '.($envDb === false ? '(unset)' : (string) $envDb),
    ]);

    fwrite(STDERR, "pre-bootstrap database guard refused\n");

    exit(3);
}

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(ConsoleKernel::class)->bootstrap();

$exitCode = 0;

try {
    // THE BOOTSTRAPPED GUARD — the ACTUAL current_database() must be the
    // literal testing database before the clock, the models or any write are
    // touched. It is compared to the LITERAL, never to the payload's word:
    // a mis-pointed cached config or env answers for itself here.
    $row = DB::selectOne('SELECT current_database() AS db');
    $currentDatabase = (is_object($row) && isset($row->db)) ? (string) $row->db : '';

    if ($currentDatabase !== 'class_replacement_testing') {
        crsWorkerWrite($payload['barrier'], $payload['role'], 'error', [
            'exception' => 'wrong_database',
            'message' => 'guard refused: current_database() = '.$currentDatabase,
        ]);

        exit(3);
    }

    // The same frozen clock as the parent, so the FR 2.17 notice gate and the
    // fixture dates agree regardless of the real wall clock. Reset on exit.
    Carbon::setTestNow(Carbon::parse((string) $payload['test_now'], 'Asia/Kuala_Lumpur'));

    $user = User::query()->findOrFail((int) $payload['user_id']);
    $session = ClassSession::query()->findOrFail((int) $payload['session_id']);

    // The barrier: mark this worker's request as created, then wait (bounded)
    // for the peer's marker BEFORE the OCC reservation can even start.
    ReplacementRequest::created(function () use ($payload): void {
        @file_put_contents($payload['barrier'].'/ready-'.$payload['role'], '1');

        $deadline = microtime(true) + CRS_WORKER_BARRIER_SECONDS;

        while (! is_file($payload['barrier'].'/ready-'.$payload['peer'])) {
            if (microtime(true) >= $deadline) {
                throw new RuntimeException('barrier_timeout: no ready marker from '.$payload['peer']);
            }

            usleep(50_000);
        }
    });

    $result = app(SubmitReplacementRequest::class)(
        $user,
        $session,
        (int) $payload['pin_week'],
        (int) $payload['anchor_slot_id'],
        null,
        (int) $payload['span'],
    );

    crsWorkerWrite($payload['barrier'], $payload['role'], 'result', $result);
} catch (Throwable $exception) {
    crsWorkerWrite($payload['barrier'], $payload['role'], 'error', [
        'exception' => $exception::class,
        'message' => $exception->getMessage(),
    ]);
    $exitCode = 2;
} finally {
    // the worker's own clock never leaks anywhere, and the backend (and its
    // session advisory locks) dies with the process
    Carbon::setTestNow();

    try {
        DB::disconnect();
    } catch (Throwable) {
        // the process exit closes the backend anyway
    }
}

exit($exitCode);
