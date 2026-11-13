<?php

namespace App\Services;

use App\Exceptions\OccurrenceWriteLockContended;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use LogicException;
use Throwable;

/**
 * SDD cancelled-slot-reuse, design D4 — the ONE encapsulated advisory-lock
 * boundary for the CRS occurrence-write serialisation.
 *
 * Key law: the key is the SOURCE class-session id (never the anchor slot id)
 * inside the fixed NAMESPACE below. The submit side takes the SESSION-level
 * `pg_try_advisory_lock` on its own connection outside any transaction and
 * releases it in a finally on the SAME connection (success, ordinary refusal
 * and thrown exception all release); the cancellation/approval side takes the
 * TRANSACTION-level `pg_try_advisory_xact_lock` inside its own transaction.
 * PostgreSQL keeps both families in one lock space, so a transaction-level
 * holder excludes the session-level taker on the same key and vice versa —
 * that is the submit-versus-cancellation/approval interlock.
 *
 * Contention NEVER blocks: both sides are try-locks that throw the dedicated
 * {@see OccurrenceWriteLockContended} before any write. The caller answers
 * with its existing refusal vocabulary and rolls back; no retry loop lives
 * here. On the transaction side any earlier-acquired locks of that
 * transaction are released when the caller's rollback lands.
 *
 * Boolean law: PDO pgsql may hand a PostgreSQL boolean back as a real PHP
 * bool, the strings 't'/'f'/'true'/'false' or an int 0/1 depending on driver
 * preparation — normalised once, so 'f' is FALSE and never a truthy string.
 *
 * Release fail-safe (D4): a failed unlock does NOT prove the connection is
 * gone — a live-but-aborted transaction still holds its session advisory
 * lock server-side while refusing every statement, the unlock included. So
 * whenever a session release cannot be VERIFIED, the exact captured
 * connection is disconnected: the backend dies and PostgreSQL drops every
 * lock it held, so the backend is never handed back for reuse with a
 * possibly-stranded session lock, and the caller's own outcome (exception
 * or result) is never masked.
 */
final class OccurrenceWriteLock
{
    /**
     * The fixed CRS occurrence-write namespace (design D4) — the ASCII letters
     * 'CRSW' packed as one big-endian integer, so the value is stable across
     * every deployment and never collides with another advisory namespace.
     */
    public const NAMESPACE = 0x43525357;

    /**
     * Try the SESSION-level advisory lock for one source class-session id on
     * the given connection (default = the application's own connection, the
     * one the submit path uses). Returns false without blocking when another
     * session or transaction already holds the key.
     */
    public function trySessionLock(int $sourceSessionId, ?string $connection = null): bool
    {
        return $this->tryOn(DB::connection($connection), $sourceSessionId);
    }

    /**
     * Release a session-level lock on the SAME connection it was taken on,
     * reporting whether the release was VERIFIED (`true` = the server
     * confirmed this session held and released the key).
     *
     * D4's fail-safe: a release failure does not prove the connection is
     * dropped (a live-but-aborted transaction can still hold the session
     * lock server-side), so every release that cannot be verified
     * DISCONNECTS the resolved connection — the backend dies and PostgreSQL
     * drops its locks — and `false` is returned. This method never throws
     * and never masks the caller's own outcome; a connection that already
     * dropped has released its session locks server-side, so the purge is
     * idempotent there.
     */
    public function releaseSessionLock(int $sourceSessionId, ?string $connection = null): bool
    {
        return $this->releaseOn(DB::connection($connection), $sourceSessionId);
    }

    /**
     * D4's submit-side scoped lock: acquire the session-level lock for the
     * source class-session id on the SAME connection the callback runs on,
     * run the callback while holding it, and release in a finally on that
     * same connection — the release happens on success, on an ordinary
     * refusal the callback returns, and on a thrown exception alike.
     *
     * The lock is transaction-independent (session-level), so the submit
     * path keeps its documented NO-outer-transaction structure and its
     * post-rollback audit/compensation contract untouched.
     *
     * The connection object is captured ONCE at acquisition and carried
     * through the finally — never re-resolved lazily by name, which could
     * hand back a different, freshly reconnected backend while the original
     * one still holds the lock server-side.
     *
     * @template TReturn
     *
     * @param  callable(): TReturn  $callback
     * @return TReturn
     *
     * @throws OccurrenceWriteLockContended BEFORE the callback runs — the
     *                                      caller maps it to its existing refusal; no write ever happened
     */
    public function runWithSessionLock(int $sourceSessionId, callable $callback, ?string $connection = null): mixed
    {
        $conn = DB::connection($connection);

        if (! $this->tryOn($conn, $sourceSessionId)) {
            throw new OccurrenceWriteLockContended($sourceSessionId);
        }

        try {
            return $callback();
        } finally {
            $this->releaseOn($conn, $sourceSessionId);
        }
    }

    /**
     * D4's cancellation/approval side: inside the CALLER'S OWN active
     * transaction, take transaction-level advisory locks for the affected
     * source class-session ids in ascending order (the D4 lock order). The
     * keys are deduplicated first, so a repeated id never contends with
     * itself.
     *
     * Contention throws {@see OccurrenceWriteLockContended} BEFORE any write;
     * the caller answers its refresh/retry refusal and rolls back — the
     * rollback also releases the ids already acquired in that transaction.
     * There is no blocking wait and no retry loop here.
     *
     * @param  list<int>  $sourceSessionIds
     * @return true
     *
     * @throws LogicException when the connection has NO active transaction —
     *                        a transaction-scoped lock taken outside a transaction would protect
     *                        nothing (design D4 forbids pretending otherwise)
     * @throws OccurrenceWriteLockContended on the first refused id
     */
    public function lockWithinTransaction(array $sourceSessionIds, ?string $connection = null): bool
    {
        $conn = DB::connection($connection);

        if ($conn->transactionLevel() === 0) {
            throw new LogicException(
                'OccurrenceWriteLock::lockWithinTransaction() requires an active database transaction (design D4).'
            );
        }

        $ids = array_values(array_unique(array_map('intval', $sourceSessionIds)));
        sort($ids);

        foreach ($ids as $sourceSessionId) {
            $row = $conn->selectOne(
                'SELECT pg_try_advisory_xact_lock(?, ?) AS acquired',
                [self::NAMESPACE, $sourceSessionId],
            );

            if (! $this->truthy($this->column($row, 'acquired'))) {
                throw new OccurrenceWriteLockContended($sourceSessionId);
            }
        }

        return true;
    }

    /**
     * The session-level acquisition against an ALREADY-RESOLVED connection —
     * the wrapper resolves once so acquisition and release always touch the
     * same backend session.
     */
    private function tryOn(Connection $conn, int $sourceSessionId): bool
    {
        $row = $conn->selectOne(
            'SELECT pg_try_advisory_lock(?, ?) AS acquired',
            [self::NAMESPACE, $sourceSessionId],
        );

        return $this->truthy($this->column($row, 'acquired'));
    }

    /**
     * The shared release body: the finally calls it with the CAPTURED
     * connection object, the public release path resolves once and
     * delegates to it.
     *
     * `true` only when the server confirms the key was released. A release
     * that throws, or whose result cannot be verified as a released key (a
     * definitive 'f' and an unusable driver shape are indistinguishable at
     * this boundary), purges the connection and returns `false` — never
     * returning a possibly-still-locked backend for reuse (D4), and never
     * masking the caller's own outcome: this method does not throw.
     */
    private function releaseOn(Connection $conn, int $sourceSessionId): bool
    {
        try {
            $row = $conn->selectOne(
                'SELECT pg_advisory_unlock(?, ?) AS released',
                [self::NAMESPACE, $sourceSessionId],
            );
        } catch (Throwable) {
            $this->purge($conn);

            return false;
        }

        if (! $this->truthy($this->column($row, 'released'))) {
            $this->purge($conn);

            return false;
        }

        return true;
    }

    /**
     * The D4 fail-safe purge: disconnect so the backend dies server-side and
     * PostgreSQL drops every session lock it held — the connection can never
     * be handed back for reuse with a stranded lock. Best-effort by design:
     * even a failing disconnect must not mask the caller's own exception
     * riding out of the enclosing finally (the worst case is the process
     * exit closing the backend).
     */
    private function purge(Connection $conn): void
    {
        try {
            $conn->disconnect();
        } catch (Throwable) {
            // the purge is best-effort; the backend closes with the process
        }
    }

    /**
     * Pull one named column out of whatever shape the PDO driver returned
     * (a stdClass row, a plain array, or something unusable), defaulting to
     * false rather than inventing an acquisition.
     */
    private function column(mixed $row, string $key): mixed
    {
        if (is_object($row)) {
            $values = (array) $row;

            return $values[$key] ?? false;
        }

        if (is_array($row)) {
            return $row[$key] ?? false;
        }

        return false;
    }

    /**
     * The boolean law: false/'f'/0/'false' are FALSE — never a truthy string.
     */
    private function truthy(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value)) {
            return $value === 1;
        }

        if (is_string($value)) {
            $normalised = strtolower(trim($value));

            return $normalised === 't' || $normalised === 'true' || $normalised === '1';
        }

        return false;
    }
}
