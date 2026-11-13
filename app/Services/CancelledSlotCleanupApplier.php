<?php

namespace App\Services;

use App\Actions\OriginalOccurrenceReleaseException;
use App\Actions\OriginalOccurrenceReleaseReceipt;
use App\Actions\ReleaseOriginalOccurrenceSlots;
use App\Exceptions\OccurrenceWriteLockContended;
use App\Models\AuditLog;
use App\Models\ClassSession;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The transactional apply of ONE approved cleanup plan (SDD
 * cancelled-slot-reuse, design D9, spec S28).
 *
 * Inside ONE transaction, in the D4 order:
 *
 *   1. the SHARED D4 lock boundary ({@see OccurrenceMutationLocks::lock()})
 *      for every scoped occurrence — included and excluded pairs alike,
 *      wherever the source session row still exists: transaction-level
 *      advisory locks on the source class-session ids, then the
 *      class_sessions, the consulted requests and the full slot union
 *      (original cells, landing cells, duplicates, out-of-range rows,
 *      receipt runs, fallback anchors), each ascending. The approved
 *      candidate union must come back fully covered by the fresh plans —
 *      anything uncovered is ownership evidence the shared planner no
 *      longer reads, and the plan fails closed rather than acquiring extra
 *      out-of-order locks;
 *   2. a FRESH inventory re-run ({@see CancelledSlotCleanupInventory});
 *   3. the fingerprint compare: ANY drift in ANY scoped ownership input — a
 *      slot version, a protected claim, an exception field, an audit row,
 *      a new or vanished occurrence — or any missing planned identity
 *      aborts the ENTIRE plan before a single write (a new dry-run and a
 *      new approval are then required);
 *   4. only approved included occurrences release their `original` cells,
 *      through the shared release action, under exact before
 *      status/owner/version conditions;
 *   5. one LEGAL maintenance audit per released occurrence
 *      (`class_cancelled` with `details.maintenance = true`, the operation
 *      id and the per-cell before/after/no-op receipt) — never a second user
 *      cancellation, and never for a zero-change occurrence.
 *
 * Notifications are never emitted: the maintenance receipt must not fan out.
 * After commit, every changed cell, protected row, exception, request link
 * and other week is re-read and verified; findings are reported, never
 * repaired here.
 *
 * The D4 lock step is DELEGATED to the cancellation lane's shared
 * `OccurrenceMutationLocks::lock()` helper — one namespace, one ordering
 * law, no second copy. The fingerprint gate stays here: the helper's
 * re-plans never replace the mandatory fresh inventory compare.
 */
final class CancelledSlotCleanupApplier
{
    public function __construct(
        private readonly CancelledSlotCleanupInventory $inventory = new CancelledSlotCleanupInventory,
        private readonly OccurrenceMutationLocks $mutationLocks = new OccurrenceMutationLocks,
    ) {}

    /**
     * Apply the approved plan. All-or-nothing: an abort leaves the database
     * byte-identical and returns an unapplied result.
     *
     * @param  CancelledSlotCleanupPlan  $approvedPlan  the fingerprint-verified plan loaded from the approved plan file
     * @param  array{operation_id: string, actor_user_id: int, approval_reference: string, approval_operator: string, expected_target: string}  $context
     */
    public function apply(CancelledSlotCleanupPlan $approvedPlan, array $context): CancelledSlotCleanupResult
    {
        try {
            $occurrences = DB::transaction(fn (): array => $this->withinTransaction($approvedPlan, $context));
        } catch (CancelledSlotCleanupAbortedException $e) {
            return new CancelledSlotCleanupResult(false, $e->reason);
        } catch (OccurrenceWriteLockContended $e) {
            return new CancelledSlotCleanupResult(false, 'lock_contended:source_session_id='.$e->sourceSessionId);
        } catch (OriginalOccurrenceReleaseException $e) {
            return new CancelledSlotCleanupResult(false, 'stale_member:'.$e->reason);
        }

        $verification = $this->verify($approvedPlan, $occurrences);

        return new CancelledSlotCleanupResult(true, null, $occurrences, $verification);
    }

    /**
     * The transaction body. Every throw rolls the whole plan back.
     *
     * @param  array{operation_id: string, actor_user_id: int, approval_reference: string, approval_operator: string, expected_target: string}  $context
     * @return list<array{class_session_id: int, week_number: int, changed: list<array<string, mixed>>, no_ops: list<array<string, mixed>>, audit_id: int|null}>
     */
    private function withinTransaction(CancelledSlotCleanupPlan $approvedPlan, array $context): array
    {
        $actualDatabase = $this->inventory->currentDatabase();

        if ($actualDatabase !== $context['expected_target']) {
            throw new CancelledSlotCleanupAbortedException('target_changed:now '.$actualDatabase);
        }

        if ($actualDatabase !== $approvedPlan->currentDatabase) {
            throw new CancelledSlotCleanupAbortedException('plan_target_mismatch');
        }

        $this->lockScope($approvedPlan);

        $fresh = $this->inventory->build();

        if ($fresh->fingerprint !== $approvedPlan->fingerprint) {
            throw new CancelledSlotCleanupAbortedException('fingerprint_drift');
        }

        if ($fresh->keys() !== $approvedPlan->keys()) {
            throw new CancelledSlotCleanupAbortedException('occurrence_set_changed');
        }

        $released = [];
        $release = new ReleaseOriginalOccurrenceSlots;

        foreach ($fresh->included() as $occurrence) {
            $plan = $occurrence->plan;

            if ($plan === null || ! $plan->isReleasable()) {
                // A drifted occurrence must already have aborted via the
                // fingerprint; this is a defensive identity check.
                throw new CancelledSlotCleanupAbortedException('missing_planned_identity:'.$occurrence->key());
            }

            $receipt = $release($plan);

            $auditId = null;

            if (! $receipt->isNoOp()) {
                $auditId = $this->maintenanceAudit($occurrence, $receipt, $context);
            }

            $released[] = [
                'class_session_id' => $occurrence->pair->classSessionId,
                'week_number' => $occurrence->pair->weekNumber,
                'changed' => $receipt->changed,
                'no_ops' => $receipt->noOps,
                'audit_id' => $auditId,
            ];
        }

        return $released;
    }

    /**
     * The D4 lock boundary over the WHOLE scoped occurrence set — included
     * and excluded pairs alike, so the re-measure reads serialised state —
     * delegated to the shared {@see OccurrenceMutationLocks::lock()} helper
     * inside THIS transaction (the helper never opens one). Every scoped
     * occurrence whose source session row still exists becomes an item; the
     * plan's consulted requests ride along as the helper's extra request
     * ids. The helper locks advisory → sessions → requests → slots, all
     * ascending, and refuses on membership drift on its own.
     */
    private function lockScope(CancelledSlotCleanupPlan $plan): void
    {
        $freshSessions = ClassSession::query()
            ->whereIn('id', $plan->sessionIds())
            ->get()
            ->keyBy('id');

        $items = [];

        foreach ($plan->occurrences as $occurrence) {
            $session = $freshSessions->get($occurrence->pair->classSessionId);

            if ($session === null) {
                // A vanished session cannot be planned; the fingerprint gate
                // owns that drift. No extra unordered locks are invented.
                continue;
            }

            $items[] = ['session' => $session, 'week' => $occurrence->pair->weekNumber];
        }

        $freshPlans = $this->mutationLocks->lock($items, $plan->consultedRequestIds());

        // Lock coverage: the helper guarantees the FRESH plans only read what
        // this transaction already holds. The APPROVED plan's full candidate
        // union (expected rows, duplicates, out-of-range rows, receipt runs,
        // fallback anchors) must be inside that too — the planner contract
        // already includes out-of-range rows, so an uncovered id is ownership
        // evidence the shared planner no longer sees. Fail closed instead of
        // acquiring extra out-of-order locks; the mandatory fingerprint gate
        // would abort the plan anyway.
        $held = [];

        foreach ($freshPlans as $freshPlan) {
            $held = array_merge($held, $freshPlan->candidateSlotIds);
        }

        $heldIds = array_flip(array_unique(array_map('intval', $held)));

        foreach ($plan->candidateSlotIds() as $slotId) {
            if (! isset($heldIds[$slotId])) {
                throw new CancelledSlotCleanupAbortedException('uncovered_candidate_locks:'.$slotId);
            }
        }
    }

    /**
     * The LEGAL maintenance receipt — the existing `class_cancelled` action,
     * marked `maintenance`, holding the operation identity and the per-cell
     * before/after/no-op evidence. It creates NO notification and is NOT
     * cancellation-source evidence for any later inventory.
     *
     * @param  array{operation_id: string, actor_user_id: int, approval_reference: string, approval_operator: string, expected_target: string}  $context
     */
    private function maintenanceAudit(CancelledSlotCleanupOccurrence $occurrence, OriginalOccurrenceReleaseReceipt $receipt, array $context): int
    {
        $audit = AuditLog::create([
            'user_id' => (int) $context['actor_user_id'],
            'action' => CancelledSlotCleanupEvidence::AUDIT_CANCELLATION,
            'details' => [
                'maintenance' => true,
                'operation_id' => (string) $context['operation_id'],
                'approval_reference' => (string) $context['approval_reference'],
                'approval_operator' => (string) $context['approval_operator'],
                'class_session_id' => $occurrence->pair->classSessionId,
                'week_number' => $occurrence->pair->weekNumber,
                'original_release' => [
                    'changed' => $receipt->changed,
                    'no_ops' => $receipt->noOps,
                ],
            ],
        ]);

        return (int) $audit->id;
    }

    /**
     * Post-commit receipt verification (spec S29): changed cells exactly as
     * released, no-op/protected cells byte-identical, request statuses and
     * junction membership intact, exceptions and recurring schedules
     * untouched, every other week's rows untouched. Findings are returned,
     * never repaired.
     *
     * @param  list<array{class_session_id: int, week_number: int, changed: list<array<string, mixed>>, no_ops: list<array<string, mixed>>, audit_id: int|null}>  $occurrences
     * @return list<string>
     */
    private function verify(CancelledSlotCleanupPlan $approvedPlan, array $occurrences): array
    {
        $findings = [];

        foreach ($occurrences as $released) {
            $key = $released['class_session_id'].':'.$released['week_number'];
            $documentOccurrence = $this->documentOccurrence($approvedPlan, $key);

            if ($documentOccurrence === null) {
                $findings[] = $key.': approved plan document lost the occurrence';

                continue;
            }

            $facts = is_array($documentOccurrence['facts'] ?? null) ? $documentOccurrence['facts'] : [];
            $entries = $this->entriesBySlotId($documentOccurrence);

            foreach ($released['changed'] as $changed) {
                $after = is_array($changed['after'] ?? null) ? $changed['after'] : [];
                $row = DB::table('time_slots')->where('id', (int) $changed['slot_id'])->first();

                if ($row === null) {
                    $findings[] = $key.': changed cell '.$changed['slot_id'].' vanished';

                    continue;
                }

                if ((string) $row->status !== 'available') {
                    $findings[] = $key.': changed cell '.$changed['slot_id'].' is not available';
                }

                if ($row->class_session_id !== null) {
                    $findings[] = $key.': changed cell '.$changed['slot_id'].' kept an owner';
                }

                if ((int) $row->version !== (int) ($after['version'] ?? -1)) {
                    $findings[] = $key.': changed cell '.$changed['slot_id'].' version drifted';
                }
            }

            foreach ($released['no_ops'] as $noOp) {
                if ($noOp['slot_id'] === null) {
                    continue;
                }

                $entry = $entries[(int) $noOp['slot_id']] ?? null;

                if ($entry === null) {
                    $findings[] = $key.': no-op cell '.$noOp['slot_id'].' missing from the approved entries';

                    continue;
                }

                $row = DB::table('time_slots')->where('id', (int) $noOp['slot_id'])->first();

                if ($row === null) {
                    $findings[] = $key.': no-op cell '.$noOp['slot_id'].' vanished';

                    continue;
                }

                if ((string) $row->status !== (string) ($entry['status'] ?? '')
                    || ($row->class_session_id !== null ? (int) $row->class_session_id : null) !== ($entry['owner_session_id'] ?? null)
                    || (int) $row->version !== (int) ($entry['version'] ?? -1)) {
                    $findings[] = $key.': protected cell '.$noOp['slot_id'].' changed';
                }
            }

            // The recurring session and every other week stay byte-identical.
            $session = DB::table('class_sessions')->where('id', $released['class_session_id'])->first();

            if ($session === null || $this->rowChanged((array) $session, is_array($facts['session'] ?? null) ? $facts['session'] : null)) {
                $findings[] = $key.': recurring session row changed';
            }

            $currentExceptions = DB::table('class_exceptions')
                ->where('class_session_id', $released['class_session_id'])
                ->where('week_number', $released['week_number'])
                ->orderBy('id')
                ->get()
                ->all();

            $currentExceptionRows = array_map(static fn ($row): array => (array) $row, $currentExceptions);
            $expectedExceptionRows = is_array($facts['exception_rows'] ?? null) ? $facts['exception_rows'] : [];

            if ($currentExceptionRows !== $expectedExceptionRows) {
                $findings[] = $key.': exception rows changed';
            }

            // Every row still owned by the source session (other weeks and
            // out-of-range rows) keeps its exact identity and version.
            $ownedRows = DB::table('time_slots')->where('class_session_id', $released['class_session_id'])->orderBy('id')->get();
            $changedIds = array_map(static fn (array $cell): int => (int) $cell['slot_id'], $released['changed']);
            $expectedOwned = [];

            foreach (is_array($facts['slots'] ?? null) ? $facts['slots'] : [] as $slotFact) {
                if (! is_array($slotFact)) {
                    continue;
                }

                if ($slotFact['class_session_id'] !== null && ! in_array((int) $slotFact['id'], $changedIds, true)) {
                    $expectedOwned[] = $slotFact;
                }
            }

            if ($this->rowsDiffer($ownedRows, $expectedOwned)) {
                $findings[] = $key.': other weeks / out-of-range rows changed';
            }

            // Active requests keep their statuses and junction membership.
            foreach (is_array($facts['requests'] ?? null) ? $facts['requests'] : [] as $requestFact) {
                if (! is_array($requestFact)) {
                    continue;
                }

                $request = DB::table('replacement_requests')->where('id', (int) $requestFact['id'])->first();

                if ($request === null) {
                    $findings[] = $key.': protected request '.$requestFact['id'].' vanished';

                    continue;
                }

                if ((string) $request->status !== (string) ($requestFact['status'] ?? '')) {
                    $findings[] = $key.': protected request '.$requestFact['id'].' status changed';
                }

                $currentJunctions = DB::table('replacement_request_slots')
                    ->where('replacement_request_id', (int) $requestFact['id'])
                    ->orderBy('id')
                    ->pluck('time_slot_id')
                    ->map(static fn ($id): int => (int) $id)
                    ->all();

                $expectedJunctions = [];

                foreach (is_array($facts['junctions'] ?? null) ? $facts['junctions'] : [] as $junctionFact) {
                    if (is_array($junctionFact) && (int) ($junctionFact['replacement_request_id'] ?? 0) === (int) $requestFact['id']) {
                        $expectedJunctions[] = (int) ($junctionFact['time_slot_id'] ?? 0);
                    }
                }

                if ($currentJunctions !== $expectedJunctions) {
                    $findings[] = $key.': protected request '.$requestFact['id'].' junction membership changed';
                }
            }
        }

        return $findings;
    }

    /**
     * One occurrence of the approved plan's document, keyed by its identity.
     *
     * @return array<string, mixed>|null
     */
    private function documentOccurrence(CancelledSlotCleanupPlan $approvedPlan, string $key): ?array
    {
        $occurrences = is_array($approvedPlan->document['occurrences'] ?? null) ? $approvedPlan->document['occurrences'] : [];

        foreach ($occurrences as $occurrence) {
            if (! is_array($occurrence)) {
                continue;
            }

            if (((string) ($occurrence['class_session_id'] ?? '')).':'.((string) ($occurrence['week_number'] ?? '')) === $key) {
                return $occurrence;
            }
        }

        return null;
    }

    /**
     * The approved plan's entries indexed by slot id.
     *
     * @param  array<string, mixed>  $documentOccurrence
     * @return array<int, array<string, mixed>>
     */
    private function entriesBySlotId(array $documentOccurrence): array
    {
        $plan = is_array($documentOccurrence['plan'] ?? null) ? $documentOccurrence['plan'] : [];
        $entries = [];

        foreach (is_array($plan['entries'] ?? null) ? $plan['entries'] : [] as $entry) {
            if (is_array($entry) && $entry['slot_id'] !== null) {
                $entries[(int) $entry['slot_id']] = $entry;
            }
        }

        return $entries;
    }

    /**
     * Whether a current row differs from its approved fact (selected content
     * fields only — timestamps never decide).
     *
     * @param  array<string, mixed>|null  $current
     * @param  array<string, mixed>|null  $expected
     */
    private function rowChanged(?array $current, ?array $expected): bool
    {
        if ($current === null || $expected === null) {
            return $current !== $expected;
        }

        foreach (['semester_id', 'module_id', 'lecturer_id', 'day_of_week', 'start_time', 'end_time', 'venue_id', 'session_type'] as $field) {
            if ((string) ($current[$field] ?? '') !== (string) ($expected[$field] ?? '')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Compare current rows against approved facts by id and version.
     *
     * @param  Collection<int, \stdClass>  $current
     * @param  list<array<string, mixed>>  $expected
     */
    private function rowsDiffer(Collection $current, array $expected): bool
    {
        $currentPairs = [];

        foreach ($current as $row) {
            $currentPairs[] = ((int) $row->id).':'.(string) $row->version;
        }

        $expectedPairs = [];

        foreach ($expected as $row) {
            $expectedPairs[] = ((int) ($row['id'] ?? 0)).':'.(string) ($row['version'] ?? '');
        }

        return $currentPairs !== $expectedPairs;
    }
}
