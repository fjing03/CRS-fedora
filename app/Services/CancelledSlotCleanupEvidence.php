<?php

namespace App\Services;

use App\Models\ClassSession;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * THE cancellation-evidence discriminator of the cleanup facility (SDD
 * cancelled-slot-reuse, design D9, spec S13/S26).
 *
 * A cancellation PAIR exists only when BOTH hold for one exact
 * `(class_session_id, week_number)`:
 *
 *   · at least one `class_cancelled` audit row that is NOT a maintenance
 *     receipt (`details.maintenance` falsy) and carries a parseable
 *     session/week identity in its details;
 *   · a CURRENT `class_exceptions` row for that same session/week.
 *
 * Everything else is deliberately NOT source evidence, exactly as D9
 * requires: an approval-only `official_event` exception stamp (no
 * cancellation audit), a maintenance `class_cancelled` receipt (this
 * facility's own receipts), and `class_cancel_undone` audits (the undo path
 * was removed; legacy undo rows never create pairs). Contradictions are
 * reported, never repaired: duplicate cancellation audits, an undo audit
 * alongside a live exception, a missing or duplicated current exception, a
 * reason mismatch between audit and exception, or a vanished session row.
 *
 * The pair-identity parse, the maintenance predicate and the
 * user-cancellation predicate are DELEGATED to the shared generic evidence
 * service {@see CancelledOccurrenceEvidence} — display and cleanup read ONE
 * rule, never a second copy. This class keeps only what is cleanup-specific:
 * the pair assembly, the stronger contradiction exclusions (duplicate
 * audits, undo evidence, reason mismatch, missing/duplicated current
 * exception, vanished session row) and the legacy-receipt skip. The current
 * exception AND the audits stay mandatory; there is no GUI coupling.
 */
final class CancelledSlotCleanupEvidence
{
    public const AUDIT_CANCELLATION = 'class_cancelled';

    public const AUDIT_UNDO = 'class_cancel_undone';

    /**
     * Every cancellation pair, sorted by class-session id then week.
     *
     * @return Collection<int, CancelledSlotCleanupPair>
     */
    public function pairs(): Collection
    {
        $rows = DB::table('audit_logs')
            ->whereIn('action', [self::AUDIT_CANCELLATION, self::AUDIT_UNDO])
            ->orderBy('id')
            ->get();

        $cancellations = [];
        $undos = [];

        foreach ($rows as $row) {
            $details = self::details($row);
            $identity = CancelledOccurrenceEvidence::pairFromDetails($details);

            if ($identity === null) {
                continue; // a legacy receipt without a readable pair identity proves nothing
            }

            $key = $identity['session'].':'.$identity['week'];

            if ($row->action === self::AUDIT_UNDO) {
                $undos[$key][] = (int) $row->id;

                continue;
            }

            if (CancelledOccurrenceEvidence::isMaintenanceDetails($details)) {
                continue; // this facility's own receipts are not cancellation evidence
            }

            $cancellations[$key][] = $row;
        }

        if ($cancellations === []) {
            return collect();
        }

        $sessionIds = [];

        foreach (array_keys($cancellations) as $key) {
            $sessionIds[] = (int) explode(':', (string) $key)[0];
        }

        $sessions = ClassSession::query()
            ->whereIn('id', $sessionIds)
            ->get()
            ->keyBy('id');

        $exceptions = DB::table('class_exceptions')
            ->whereIn('class_session_id', $sessionIds)
            ->orderBy('id')
            ->get()
            ->groupBy(fn ($row): string => ((int) $row->class_session_id).':'.((int) $row->week_number));

        $pairs = [];

        foreach ($cancellations as $key => $auditRows) {
            [$sessionId, $week] = explode(':', (string) $key);
            $sessionId = (int) $sessionId;
            $week = (int) $week;

            $auditIds = array_map(fn ($row): int => (int) $row->id, $auditRows);
            $details = self::details($auditRows[0]);
            $reason = (string) ($details['reason'] ?? '');

            $contradiction = null;

            if (count($auditRows) > 1) {
                $contradiction = 'duplicate_cancellation_audit';
            }

            if ($contradiction === null && isset($undos[$key])) {
                $contradiction = 'undo_evidence_present';
            }

            $exceptionRows = $exceptions->get($key, collect())->all();
            $exceptionId = null;
            $exceptionReason = null;

            if ($exceptionRows === []) {
                if ($contradiction === null) {
                    $contradiction = 'missing_current_exception';
                }
            } elseif (count($exceptionRows) > 1) {
                if ($contradiction === null) {
                    $contradiction = 'duplicate_exception';
                }
            } else {
                $exceptionId = (int) $exceptionRows[0]->id;
                $exceptionReason = (string) $exceptionRows[0]->reason;

                if ($reason !== '' && $reason !== $exceptionReason && $contradiction === null) {
                    $contradiction = 'reason_mismatch';
                }
            }

            $session = $sessions->get($sessionId);

            if ($session === null && $contradiction === null) {
                $contradiction = 'missing_session';
            }

            $pairs[] = new CancelledSlotCleanupPair(
                $sessionId,
                $session !== null ? (int) $session->semester_id : 0,
                $week,
                $reason,
                $auditIds,
                $undos[$key] ?? [],
                $exceptionId,
                $exceptionReason,
                $contradiction,
            );
        }

        usort($pairs, fn (CancelledSlotCleanupPair $a, CancelledSlotCleanupPair $b): int => [$a->classSessionId, $a->weekNumber] <=> [$b->classSessionId, $b->weekNumber]);

        return collect($pairs);
    }

    /**
     * The maintenance flag of a `class_cancelled` audit's details (truthy =
     * this facility's own receipt) — delegated to the shared generic
     * evidence service, never a second copy of the rule.
     *
     * @param  array<string, mixed>  $details
     */
    public static function isMaintenanceAuditDetails(array $details): bool
    {
        return CancelledOccurrenceEvidence::isMaintenanceDetails($details);
    }

    /**
     * The shared predicate on ONE audit row: a NON-maintenance,
     * identity-readable `class_cancelled` row — delegated to the shared
     * generic evidence service ({@see CancelledOccurrenceEvidence}). The
     * cleanup inventory reads raw `stdClass` rows from `audit_logs`.
     */
    public static function isUserCancellationAudit(\stdClass $audit): bool
    {
        return CancelledOccurrenceEvidence::isUserCancellationAudit((string) $audit->action, self::details($audit));
    }

    /**
     * The details JSON of a RAW `audit_logs` row (the query builder's
     * `stdClass`): PostgreSQL hands the `details` jsonb over as a JSON
     * string, and the decode is real runtime validation — an undecodable or
     * non-array payload is an empty map, never a cast lie.
     *
     * @return array<string, mixed>
     */
    public static function details(\stdClass $audit): array
    {
        $raw = $audit->details;

        if (is_array($raw)) {
            return $raw;
        }

        if (is_object($raw)) {
            return (array) $raw;
        }

        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);

            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }
}
