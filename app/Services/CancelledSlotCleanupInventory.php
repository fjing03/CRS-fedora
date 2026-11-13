<?php

namespace App\Services;

use App\Models\ClassSession;
use Illuminate\Support\Facades\DB;

/**
 * The SELECT-only inventory of the cleanup facility (SDD cancelled-slot-reuse,
 * design D9, spec S26/S28).
 *
 * For every cancellation pair ({@see CancelledSlotCleanupEvidence}) it runs
 * the shared original-occurrence planner ({@see OriginalOccurrenceReservation}
 * — the ONE classifier, never a copied rule), includes only clean and
 * unambiguous occurrences, and gathers the scoped ownership facts (session
 * row, exception rows, every consulted audit, exact slot rows including
 * duplicates and out-of-range rows, active requests with full junction
 * membership) that the fingerprint hashes.
 *
 * It never writes and never locks. The applier calls it once provisionally
 * and again INSIDE its transaction after the D4 locks — the second plan is
 * the one fingerprint-compared against the approved plan file.
 */
final class CancelledSlotCleanupInventory
{
    public function __construct(
        private readonly OriginalOccurrenceReservation $planner = new OriginalOccurrenceReservation,
        private readonly CancelledSlotCleanupEvidence $evidence = new CancelledSlotCleanupEvidence,
    ) {}

    /**
     * Build the full fingerprinted inventory. Re-callable inside a
     * transaction; the fingerprint must then be compared with the approved
     * plan before any write (any drift aborts the whole plan).
     */
    public function build(): CancelledSlotCleanupPlan
    {
        $occurrences = [];

        foreach ($this->evidence->pairs() as $pair) {
            $occurrences[] = $this->occurrence($pair);
        }

        $document = [
            'algorithm' => CancelledSlotCleanupFingerprint::ALGORITHM,
            'current_database' => $this->currentDatabase(),
            'occurrences' => array_map($this->documentOccurrence(...), $occurrences),
        ];

        return new CancelledSlotCleanupPlan(
            $occurrences,
            $document['current_database'],
            CancelledSlotCleanupFingerprint::hashDocument($document),
            $document,
        );
    }

    /** The live target identity the plan is bound to (spec S26: printed and checked, never assumed). */
    public function currentDatabase(): string
    {
        $row = DB::selectOne('SELECT current_database() AS db');

        $name = is_object($row) ? ($row->db ?? null) : null;

        return $name === null ? '' : (string) $name;
    }

    private function occurrence(CancelledSlotCleanupPair $pair): CancelledSlotCleanupOccurrence
    {
        $session = ClassSession::query()->find($pair->classSessionId);

        if ($session === null) {
            return new CancelledSlotCleanupOccurrence(
                $pair,
                null,
                false,
                $pair->contradiction ?? 'missing_session',
                $this->factsFor($pair, null),
            );
        }

        $plan = $this->planner->plan($session, $pair->weekNumber);

        if ($pair->contradiction !== null) {
            return new CancelledSlotCleanupOccurrence($pair, $plan, false, $pair->contradiction, $this->factsFor($pair, $plan));
        }

        if ($plan->hasRefusal()) {
            return new CancelledSlotCleanupOccurrence($pair, $plan, false, $plan->refusalReason(), $this->factsFor($pair, $plan));
        }

        return new CancelledSlotCleanupOccurrence($pair, $plan, true, null, $this->factsFor($pair, $plan));
    }

    /**
     * The scoped facts of one occurrence — everything the fingerprint must
     * cover, read raw (no Carbon objects, no model casts) so the canonical
     * JSON is byte-stable across reads: source/exception fields, exact slot
     * rows including duplicates and out-of-range rows, active requests with
     * their full junction membership, and every relevant audit row.
     *
     * @return array{session: array<string, mixed>|null, exception_rows: list<array<string, mixed>>, audits: list<array<string, mixed>>, slots: list<array<string, mixed>>, requests: list<array<string, mixed>>, junctions: list<array<string, mixed>>}
     */
    private function factsFor(CancelledSlotCleanupPair $pair, ?OriginalOccurrencePlan $plan): array
    {
        // `??` already handles the null plan (isset semantics), so the plain
        // arrow is the honest form — the coalesced fallbacks stay.
        $slotIds = $plan->candidateSlotIds ?? [];
        $requestIds = $plan->claimingRequestIds ?? [];
        $sessionId = $pair->classSessionId;

        $session = DB::table('class_sessions')->where('id', $sessionId)->first();
        $exceptionRows = DB::table('class_exceptions')
            ->where('class_session_id', $sessionId)
            ->where('week_number', $pair->weekNumber)
            ->orderBy('id')
            ->get()
            ->all();

        $auditsQuery = DB::table('audit_logs');

        if ($slotIds !== []) {
            $auditsQuery->where(function ($query) use ($slotIds, $sessionId, $pair): void {
                $query->whereIn('time_slot_id', $slotIds)
                    ->orWhere(function ($sub) use ($sessionId, $pair): void {
                        $sub->whereIn('action', [CancelledSlotCleanupEvidence::AUDIT_CANCELLATION, CancelledSlotCleanupEvidence::AUDIT_UNDO])
                            ->whereRaw("details->>'class_session_id' = ?", [(string) $sessionId])
                            ->whereRaw("details->>'week_number' = ?", [(string) $pair->weekNumber]);
                    });
            });
        } else {
            $auditsQuery->whereIn('action', [CancelledSlotCleanupEvidence::AUDIT_CANCELLATION, CancelledSlotCleanupEvidence::AUDIT_UNDO])
                ->whereRaw("details->>'class_session_id' = ?", [(string) $sessionId])
                ->whereRaw("details->>'week_number' = ?", [(string) $pair->weekNumber]);
        }

        $audits = $auditsQuery->orderBy('id')->get()->all();

        $slots = $slotIds === []
            ? []
            : DB::table('time_slots')->whereIn('id', $slotIds)->orderBy('id')->get()->all();

        $requests = $requestIds === []
            ? []
            : DB::table('replacement_requests')->whereIn('id', $requestIds)->orderBy('id')->get()->all();

        $junctions = $requestIds === []
            ? []
            : DB::table('replacement_request_slots')
                ->whereIn('replacement_request_id', $requestIds)
                ->orderBy('id')
                ->get()
                ->all();

        return [
            'session' => $session !== null ? (array) $session : null,
            'exception_rows' => $this->rows($exceptionRows),
            'audits' => $this->rows($audits),
            'slots' => $this->rows($slots),
            'requests' => $this->rows($requests),
            'junctions' => $this->rows($junctions),
        ];
    }

    /**
     * Raw rows as plain maps — the fingerprint's stable scalar facts. The
     * query builder hands every row over as a plain `stdClass`.
     *
     * @param  array<int, \stdClass>  $rows
     * @return list<array<string, mixed>>
     */
    private function rows(array $rows): array
    {
        $maps = [];

        foreach ($rows as $row) {
            $maps[] = (array) $row;
        }

        return $maps;
    }

    /**
     * The document projection of one occurrence — the exact fingerprint
     * payload (S26: expected slot identities, classification, ownership,
     * versions, no-ops, exclusions, scoped facts).
     *
     * @return array<string, mixed>
     */
    private function documentOccurrence(CancelledSlotCleanupOccurrence $occurrence): array
    {
        $pair = $occurrence->pair;
        $plan = $occurrence->plan;

        $entries = [];

        foreach ($plan->entries ?? [] as $entry) {
            $entries[] = [
                'start_time' => $entry->startTime,
                'end_time' => $entry->endTime,
                'classification' => $entry->classification,
                'slot_id' => $entry->slotId,
                'status' => $entry->status,
                'owner_session_id' => $entry->ownerSessionId,
                'version' => $entry->version,
                'duplicate_slot_ids' => $entry->duplicateSlotIds,
                'held_by_request_id' => $entry->heldByRequestId,
                'reason' => $entry->reason,
            ];
        }

        return [
            'class_session_id' => $pair->classSessionId,
            'semester_id' => $pair->semesterId,
            'week_number' => $pair->weekNumber,
            'day_of_week' => $plan->dayOfWeek ?? 0,
            'venue_id' => $plan->venueId ?? 0,
            'included' => $occurrence->included,
            'exclusion_reason' => $occurrence->exclusionReason,
            'evidence' => [
                'audit_ids' => $pair->auditIds,
                'undo_audit_ids' => $pair->undoAuditIds,
                'audit_reason' => $pair->reason,
                'exception_id' => $pair->exceptionId,
                'exception_reason' => $pair->exceptionReason,
                'contradiction' => $pair->contradiction,
            ],
            'plan' => [
                'refusal_reason' => $plan?->refusalReason,
                'entries' => $entries,
                'out_of_range' => $plan->outOfRange ?? [],
                'candidate_slot_ids' => $plan->candidateSlotIds ?? [],
                'claiming_request_ids' => $plan->claimingRequestIds ?? [],
            ],
            'facts' => $occurrence->facts,
        ];
    }
}
