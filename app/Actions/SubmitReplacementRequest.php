<?php

namespace App\Actions;

use App\Models\ClassSession;
use App\Models\ReplacementRequest;
use App\Models\TimeSlot;
use App\Models\User;
use App\Services\MatrixIntersectionEngine;
use App\Services\OCCValidator;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Submit a replacement request (FR 2.9) with OCC reservation (FR 4.8–4.10)
 * and the D5 compensating-delete contract.
 *
 * Design: NO outer transaction around request-create + reserve — the validator
 * commits its own audit rows; nesting would turn them into a savepoint. On any
 * loser outcome the compensation runs in the D5 order: pre-null
 * audit_logs.replacement_request_id (the conflict audit row SURVIVES — FR 4.12),
 * THEN delete the request row. The audit row itself is never touched.
 */
final class SubmitReplacementRequest
{
    /** Full-block bound (frozen proposal item 3): FOCS blocks run 0.5–2h. */
    public const SPAN_MIN = 1;

    public const SPAN_MAX = 4;

    public function __construct(
        private readonly OCCValidator $occ,
        private readonly MatrixIntersectionEngine $engine,
    ) {}

    /**
     * Full-block signature (SDD full-block-reservation, design D2): $span is
     * the block width in 30-min slots (anchor..anchor+span−1). Existing
     * callers pass nothing — span 1 = the legacy single-slot reservation,
     * byte-identical.
     *
     * @return array{ok: bool, conflict_reason: string|null, request_id: int|null, failing_start: string|null, failing_end: string|null, failing_kind: string|null}
     */
    public function __invoke(User $proposer, ClassSession $session, int $weekNumber, int $anchorSlotId, ?string $remarks = null, int $span = 1): array
    {
        $fail = function (string $reason, ?string $failingStart = null, ?string $failingEnd = null, ?string $failingKind = null): array {
            return ['ok' => false, 'conflict_reason' => $reason, 'request_id' => null, 'failing_start' => $failingStart, 'failing_end' => $failingEnd, 'failing_kind' => $failingKind];
        };

        // FR 2.11 — ownership scoping: a lecturer submits for own sessions only.
        if ((int) $session->lecturer_id !== (int) $proposer->id) {
            return $fail('not_session_owner');
        }

        // FR 2.9 — replacement weeks are 1–14 (schema CHECK + request column).
        if ($weekNumber < 1 || $weekNumber > 14) {
            return $fail('invalid_week');
        }

        // Full-block bounds (frozen proposal item 3; round-5 fold: BEFORE any
        // business pre-check, no write, no audit row — the invalid_week shape).
        if ($span < self::SPAN_MIN || $span > self::SPAN_MAX) {
            return $fail('invalid_span');
        }

        $slot = TimeSlot::query()->find($anchorSlotId);
        if ($slot === null) {
            return $fail('slot_not_found');
        }

        // Pre-check: anchor must still be available (the OCC validator re-checks
        // under lock — the race window between here and there is the loser path).
        if ($slot->status !== 'available') {
            return $fail('slot_not_available');
        }

        // Span pre-resolve (design D2 step 3 — the steady-state named-time path,
        // user amendment 2026-10-16 optional-B): every planned 30-min cell must
        // exist AND be available. The failing slot's facts + the failing_kind
        // discriminator ('occupied' row-backed vs 'missing' derived-expected)
        // ride the flat return.
        $plannedStarts = [];
        for ($k = 0; $k < $span; $k++) {
            $plannedStarts[] = Carbon::parse((string) $slot->start_time)->addMinutes($k * 30)->format('H:i:s');
        }

        $spanSlots = TimeSlot::query()
            ->where('semester_id', $slot->semester_id)
            ->where('week_number', $slot->week_number)
            ->where('venue_id', $slot->venue_id)
            ->where('day_of_week', $slot->day_of_week)
            ->whereIn('start_time', $plannedStarts)
            ->orderBy('start_time')
            ->get();

        foreach ($plannedStarts as $plannedStart) {
            $plannedEnd = Carbon::parse($plannedStart)->addMinutes(30)->format('H:i:s');
            $spanSlot = $spanSlots->firstWhere('start_time', $plannedStart);

            if ($spanSlot === null) {
                // No row to read — the derived EXPECTED time; the routing table
                // renders the bounds-style copy (a booking must not be claimed).
                return $fail('slot_not_available', $plannedStart, $plannedEnd, 'missing');
            }

            if ($spanSlot->status !== 'available') {
                return $fail('slot_not_available', (string) $spanSlot->start_time, (string) $spanSlot->end_time, 'occupied');
            }
        }

        // Engine pre-check (FR 4.3–4.7) across the FULL span: lecturer/cohort
        // busy vectors at ANY cell (incl. tails) reject pre-reserve.
        $cohortIds = $session->cohorts->pluck('id')->all();
        if (! $this->engine->validateSlot($anchorSlotId, (int) $proposer->id, $cohortIds, $session->session_type, (int) $slot->week_number, $span)) {
            return $fail('engine_precheck_failed');
        }

        $request = ReplacementRequest::create([
            'semester_id' => $slot->semester_id,
            'proposer_id' => $proposer->id,
            'class_session_id' => $session->id,
            'week_number' => $weekNumber,
            'replacement_time_slot_id' => $slot->id,
            'status' => 'pending',
            'remarks' => $remarks !== null && trim($remarks) !== '' ? trim($remarks) : null,
            'submitted_at' => now(),
        ]);

        try {
            $result = $this->occ->validateAndReserve($anchorSlotId, (int) $proposer->id, (int) $request->id, $span);
        } catch (RuntimeException $exception) {
            // Loser shapes: (a) lock-timeout aborts under a concurrent holder
            // (PDO lock_timeout → PDOException, a RuntimeException subclass) and
            // (b) the two-phase validator's peri-flip rollback — a RuntimeException
            // subtype re-thrown from the validator's catch (design D3's rollback
            // contract). Either way: D5 compensation — a loser never 500s — and
            // NOTHING is re-thrown (this catch stays the sole surfaced loser path).
            $this->compensateLoser((int) $request->id);

            return $fail('reserve_interrupted');
        }

        if (! $result->success) {
            $this->compensateLoser((int) $request->id);

            // The OCC-race named-time contract (design D6): the action is the
            // reader of the extended OCCResult signature — copies the failing
            // slot's times into the flat return and stamps failing_kind
            // 'occupied' UNCONDITIONALLY (a raced row always exists).
            return $fail((string) $result->conflictReason, $result->failingStart, $result->failingEnd, 'occupied');
        }

        return ['ok' => true, 'conflict_reason' => null, 'request_id' => (int) $request->id, 'failing_start' => null, 'failing_end' => null, 'failing_kind' => null];
    }

    /**
     * D5 compensating delete — order matters: pre-null the audit FK first (the
     * validator's conflict audit row survives, FR 4.12), THEN delete the request
     * row. Deleting or nulling the audit row itself is FORBIDDEN. The pair is
     * atomic; it does not wrap the validator's already-committed transaction.
     */
    private function compensateLoser(int $requestId): void
    {
        DB::transaction(function () use ($requestId): void {
            DB::statement('UPDATE audit_logs SET replacement_request_id = NULL WHERE replacement_request_id = ?', [$requestId]);
            ReplacementRequest::query()->whereKey($requestId)->delete();
        });
    }
}
