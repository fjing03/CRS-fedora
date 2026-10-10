<?php

namespace App\Actions;

use App\Models\ClassSession;
use App\Models\ReplacementRequest;
use App\Models\TimeSlot;
use App\Models\User;
use App\Services\MatrixIntersectionEngine;
use App\Services\OCCValidator;
use Illuminate\Support\Facades\DB;

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
    public function __construct(
        private readonly OCCValidator $occ,
        private readonly MatrixIntersectionEngine $engine,
    ) {}

    /**
     * @return array{ok: bool, conflict_reason: string|null, request_id: int|null}
     */
    public function __invoke(User $proposer, ClassSession $session, int $weekNumber, int $anchorSlotId, ?string $remarks = null): array
    {
        $fail = fn (string $reason): array => ['ok' => false, 'conflict_reason' => $reason, 'request_id' => null];

        // FR 2.11 — ownership scoping: a lecturer submits for own sessions only.
        if ((int) $session->lecturer_id !== (int) $proposer->id) {
            return $fail('not_session_owner');
        }

        // FR 2.9 — replacement weeks are 1–14 (schema CHECK + request column).
        if ($weekNumber < 1 || $weekNumber > 14) {
            return $fail('invalid_week');
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

        // Engine pre-check (FR 4.3–4.7): lecturer free, cohorts free, venue fits.
        $cohortIds = $session->cohorts->pluck('id')->all();
        if (! $this->engine->validateSlot($anchorSlotId, (int) $proposer->id, $cohortIds, $session->session_type, (int) $slot->week_number)) {
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
            $result = $this->occ->validateAndReserve($anchorSlotId, (int) $proposer->id, (int) $request->id);
        } catch (\RuntimeException $exception) {
            // Two loser shapes land here: (a) the validator's own OCCResult
            // conflicts return normally below — this catch is for (b) lock-wait
            // aborts under a concurrent holder (PostgreSQL lock_timeout fires
            // inside the validator's SELECT … FOR UPDATE → PDOException, a
            // RuntimeException subclass). Either way: map to the FR 4.10
            // conflict alert with D5 compensation — a loser must never
            // surface as a 500.
            $this->compensateLoser((int) $request->id);

            return $fail('reserve_interrupted');
        }

        if (! $result->success) {
            $this->compensateLoser((int) $request->id);

            return $fail((string) $result->conflictReason);
        }

        return ['ok' => true, 'conflict_reason' => null, 'request_id' => (int) $request->id];
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
