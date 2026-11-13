<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\ClassException;
use Illuminate\Support\Collection;

/**
 * THE shared event-identity + cancellation-provenance contract
 * (SDD cancelled-slot-reuse A11 — design D5, specs S13/S14/R4).
 *
 * Two jobs, one home — every timetable payload builder (the
 * ResolvesTimetableTimeline pages AND VenueTimetable) and the later
 * `classes:release-cancelled-slots` CLI read the SAME public API:
 *
 * 1. STABLE EVENT IDENTITY (D5). Every payload event carries an `eventKey`
 *    the JS consumes instead of inventing identities from coordinates:
 *
 *        occ:<semester>:<class_session>:<week>   an original occurrence
 *        request:<requestId>                     a landing request event
 *
 *    Same coordinates, same source session or same start NEVER mean the same
 *    event — distinct keys never merge, and combined-cohort duplicates of the
 *    SAME key merge their cohort facts ONCE ({@see dedupeSameKey()}).
 *
 * 2. THE S13 CANCELLATION DISCRIMINATOR. A pair is a REAL user cancellation
 *    only when BOTH halves agree on the exact (class_session, week):
 *
 *        a current class_exceptions row
 *        + a NON-maintenance `class_cancelled` audit naming that pair
 *
 *    `official_event` alone is NOT proof — the approval flow stamps that same
 *    reason value (ApproveRequest D3), so a stamp-only pair is reported as
 *    {@see PROVENANCE_STAMP_ONLY} and never gains cancelled history from
 *    overlap. Maintenance receipts (the cleanup CLI's `maintenance = true`
 *    audits) are not user evidence either. Missing/contradictory evidence is
 *    REPORTED ({@see PROVENANCE_AUDIT_ONLY}), never repaired or invented.
 *
 * The service is READ-ONLY: it never writes, never migrates, never decides
 * availability.
 */
final class TimetableEventIdentity
{
    /** Exception + non-maintenance audit: a proven user cancellation (S13). */
    public const PROVENANCE_USER_CANCELLED = 'user_cancelled';

    /**
     * Exception without a user audit — the approval stamp (or a pair whose
     * audit is missing/maintenance-only). Existing suppression unchanged.
     */
    public const PROVENANCE_STAMP_ONLY = 'stamp_only';

    /**
     * Audit without a current exception — contradictory evidence. Reported,
     * never synthesised into a cancellation (S13).
     */
    public const PROVENANCE_AUDIT_ONLY = 'audit_only';

    public const PROVENANCE_NONE = 'none';

    /** The shipped venue twin severity order (conflict > pending > replacement > normal). */
    private const SEVERITY = ['normal' => 0, 'replacement' => 1, 'pending' => 2, 'conflict' => 3];

    /** The original-occurrence identity: `occ:<semester>:<session>:<week>`. */
    public static function occurrenceKey(int $semesterId, int $classSessionId, int $weekNumber): string
    {
        return 'occ:'.$semesterId.':'.$classSessionId.':'.$weekNumber;
    }

    /** The landing-request identity: `request:<requestId>`. */
    public static function requestKey(int $requestId): string
    {
        return 'request:'.$requestId;
    }

    /**
     * Additive tag — sets `eventKey` and touches NOTHING else, so every
     * existing id/sessionId/requestId and modal field survives unchanged.
     *
     * @param  array<string, mixed>  $event
     * @return array<string, mixed>
     */
    public static function tag(array $event, string $eventKey): array
    {
        $event['eventKey'] = $eventKey;

        return $event;
    }

    /**
     * The audit half of the S13 discriminator, batched AND SCOPED: which of
     * these class sessions carry a NON-maintenance `class_cancelled` audit
     * naming an exact (session, week) pair. ONE read whose JSON
     * `details->class_session_id` is bound to the REQUESTED session ids — a
     * session outside the scope never leaks into the result — and every
     * returned key is validated by the shared
     * {@see CancelledOccurrenceEvidence} predicates (same rule the cleanup
     * facility reads).
     *
     * @param  Collection<int, int|string>|array<int, int|string>  $classSessionIds
     * @return array<string, true> keyed "<class_session_id>:<week_number>"
     */
    public static function userCancelledAuditPairs(Collection|array $classSessionIds): array
    {
        $ids = collect($classSessionIds)->map(fn ($v): int => (int) $v)->unique()->values();
        if ($ids->isEmpty()) {
            return [];
        }

        $out = [];
        AuditLog::query()
            ->where('action', CancelledOccurrenceEvidence::AUDIT_CANCELLATION)
            ->whereIn('details->class_session_id', $ids->map(fn (int $id): string => (string) $id)->all())
            ->get(['details'])
            ->each(function (AuditLog $audit) use (&$out): void {
                $details = $audit->details;
                if (! is_array($details)) {
                    return;   // malformed evidence is not proof either
                }
                $pair = CancelledOccurrenceEvidence::pairFromDetails($details);
                if ($pair === null
                    || ! CancelledOccurrenceEvidence::isUserCancellationAudit(
                        CancelledOccurrenceEvidence::AUDIT_CANCELLATION, $details,
                    )) {
                    return;   // maintenance receipts and unreadable pairs prove nothing
                }
                $out[$pair['session'].':'.$pair['week']] = true;
            });

        return $out;
    }

    /**
     * The S13 verdict for ONE (class_session, week) pair — the SAME
     * discriminator the payload builders and the later cleanup CLI share.
     *
     * @return string One of this class's PROVENANCE_* constants.
     */
    public static function provenance(?ClassException $exception, bool $hasUserCancelAudit): string
    {
        return match (true) {
            $exception !== null && $hasUserCancelAudit => self::PROVENANCE_USER_CANCELLED,
            $exception !== null => self::PROVENANCE_STAMP_ONLY,
            $hasUserCancelAudit => self::PROVENANCE_AUDIT_ONLY,
            default => self::PROVENANCE_NONE,
        };
    }

    /**
     * S16's overlap half: does ANY in-scope active event interval overlap the
     * original occurrence's (day, 30-min index range)? Intervals use the
     * payload's inclusive `start..end` slot indexes with `di` = day index.
     *
     * @param  list<array{di: int|string, start: int|string, end: int|string}>  $intervals
     */
    public static function overlapsOriginal(array $intervals, int $day, int $start, int $end): bool
    {
        foreach ($intervals as $interval) {
            if ((int) $interval['di'] !== $day) {
                continue;
            }
            if ((int) $interval['start'] <= $end && (int) $interval['end'] >= $start) {
                return true;
            }
        }

        return false;
    }

    /**
     * D5/S14 deduplication: merge duplicates of the SAME `eventKey` exactly
     * once, preserving first-seen order. Events without a readable key are
     * never merged with anything (an unverifiable identity cannot be proven
     * to be the same event — distinct identities never merge). Same
     * coordinates or same start with DIFFERENT keys pass through untouched.
     *
     * @param  list<array<string, mixed>>  $events
     * @return list<array<string, mixed>>
     */
    public static function dedupeSameKey(array $events): array
    {
        $groups = [];
        $indexByKey = [];
        foreach ($events as $event) {
            $candidate = $event['eventKey'] ?? null;
            $key = is_string($candidate) && $candidate !== '' ? $candidate : null;
            if ($key === null) {
                $groups[] = [$event];   // unkeyed → its own identity, never merged

                continue;
            }
            if (isset($indexByKey[$key])) {
                $groups[$indexByKey[$key]][] = $event;

                continue;
            }
            $indexByKey[$key] = count($groups);
            $groups[] = [$event];
        }

        return array_map(
            fn (array $group): array => count($group) === 1 ? $group[0] : self::mergeSameKey($group),
            $groups,
        );
    }

    /**
     * Merge a list of SAME-key duplicates into ONE event (the shipped
     * VenueTimetable `reduceTwins` contract, identity-gated by the caller):
     * status by severity, cohort label joined, cohort codes unioned with
     * POSITIONAL student counts, `mine` = OR. A twin that brings NO new
     * cohort adds NO student count — an exact duplicate (the same occurrence
     * seen twice) must not inflate the totals (D7).
     *
     * @param  list<array<string, mixed>>  $twins
     * @return array<string, mixed>
     */
    public static function mergeSameKey(array $twins): array
    {
        $merged = $twins[0];

        foreach (array_slice($twins, 1) as $twin) {
            if (self::severityOf($twin) > self::severityOf($merged)) {
                $merged['status'] = $twin['status'];
            }
            if (str_contains((string) $merged['cohort'], (string) $twin['cohort']) === false) {
                $merged['cohort'] = trim((string) $merged['cohort'].' + '.$twin['cohort'], ' +');
            }
            foreach ($twin['cohorts'] ?? [] as $ci => $cohortCode) {
                if (in_array($cohortCode, $merged['cohorts'] ?? [], true)) {
                    continue;   // already-merged cohort facts contribute nothing twice
                }
                $merged['cohorts'][] = $cohortCode;
                // keep studentCounts POSITIONAL with the unioned cohorts
                // (BMSE3153-class-info: the modal's A + B = total reads the
                // pair by index) — a newly added cohort brings its own count.
                $added = (int) ($twin['studentCounts'][$ci] ?? 0);
                $merged['studentCounts'] = array_merge($merged['studentCounts'] ?? [], [$added]);
                $merged['studentCount'] = (int) ($merged['studentCount'] ?? 0) + $added;
            }
            $merged['mine'] = ($merged['mine'] ?? false) || ($twin['mine'] ?? false);
        }

        return $merged;
    }

    /** @param  array<string, mixed>  $event */
    private static function severityOf(array $event): int
    {
        return self::SEVERITY[$event['status'] ?? ''] ?? 0;
    }
}
