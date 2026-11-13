<?php

namespace App\Services;

use App\Actions\OriginalOccurrenceReleaseException;
use App\Exceptions\OccurrenceWriteLockContended;
use App\Models\ClassSession;
use App\Models\ReplacementRequest;
use App\Models\TimeSlot;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * THE occurrence-mutation lock boundary (SDD cancelled-slot-reuse, design
 * D4) — one helper that every mutating path (cancellation, approval) calls
 * INSIDE its own transaction before any write:
 *
 *   1. transaction-level advisory locks for every affected SOURCE class
 *      session id, ascending (the fixed CRS namespace via
 *      {@see OccurrenceWriteLock} — contention throws before any write);
 *   2. the affected `class_sessions` rows, ascending id;
 *   3. the complete request union, ascending id — the items' own active
 *      source requests, the provisional plans' live claims, and every extra
 *      request id the caller passes (the approval path's deciding request);
 *   4. the complete slot union, ascending id — the provisional plans'
 *      candidate cells plus EVERY union request's landing cells (junction
 *      members, or the legacy anchor/span fallback via
 *      {@see OriginalOccurrenceReservation::validateRequestClaim()}).
 *
 * Provisional discovery is only a lock-set estimate read from PRE-LOCK
 * state. After the request rows are locked, EVERY union request's claim
 * identity is re-derived FRESH (source session, anchor, stored span, the
 * complete landing set via the shared claim validation) and must match the
 * provisional capture EXACTLY — a subset would let a receipt that gained,
 * lost or moved landing cells slip a stale union past the locks and make a
 * nested release acquire slots out of order. Any difference is membership
 * drift and the helper refuses with
 * {@see OriginalOccurrenceReleaseException} before the slots are locked —
 * the caller rolls back and answers its refresh/retry refusal. The items'
 * own active source requests are re-read under the locks too, and must be
 * the SAME set (the occurrence's attached-request membership is unchanged,
 * never merely a subset). The helper NEVER acquires extra lower-order locks
 * out of order, and NEVER judges a plan's own refusals (missing/ambiguous
 * cells): those ride home in the returned plans and the caller decides
 * which one matters.
 *
 * No outer transaction is opened here — the CALLER owns the transaction,
 * exactly as `ReleaseOriginalOccurrenceSlots` requires.
 */
final class OccurrenceMutationLocks
{
    public function __construct(
        private readonly OriginalOccurrenceReservation $planner = new OriginalOccurrenceReservation,
        private readonly OccurrenceWriteLock $writeLock = new OccurrenceWriteLock,
    ) {}

    /**
     * Lock the complete mutation union and return the FRESH post-lock plan of
     * every item, keyed `"{classSessionId}:{week}"`.
     *
     * @param  list<array{session: ClassSession, week: int}>  $items  the occurrences about to be mutated
     * @param  list<int>  $requestIds  extra requests the caller will close (already validated; locked here so nested helpers reuse the held locks)
     * @return array<string, OriginalOccurrencePlan>
     *
     * @throws LogicException when there is NO active caller transaction
     * @throws OccurrenceWriteLockContended before any write
     * @throws OriginalOccurrenceReleaseException on membership drift
     */
    public function lock(array $items, array $requestIds = []): array
    {
        if (DB::transactionLevel() < 1) {
            throw new LogicException(
                'OccurrenceMutationLocks::lock() requires the caller\'s active transaction (design D4).'
            );
        }

        // ── provisional discovery (SELECT-only; a lock-set estimate only) ──
        $provisionalPlans = [];
        $sessionIds = [];
        $requestIds = array_values(array_unique(array_map('intval', $requestIds)));
        $itemSourceRequestIds = [];

        foreach ($items as $item) {
            $session = $item['session'];
            $week = (int) $item['week'];
            $sessionIds[] = (int) $session->id;
            $plan = $this->planner->plan($session, $week);
            $provisionalPlans[$this->key((int) $session->id, $week)] = $plan;
            $requestIds = array_values(array_unique(array_merge($requestIds, $plan->claimingRequestIds)));
        }

        // the items' own active source requests (closed by the caller) join
        // the union before anything is locked — their exact membership is
        // captured now and re-checked EQUAL under the locks
        foreach ($items as $item) {
            $itemKey = $this->key((int) $item['session']->id, (int) $item['week']);
            $source = $this->activeSourceRequests((int) $item['session']->id, (int) $item['week']);
            $ids = [];

            foreach ($source as $request) {
                $ids[] = (int) $request->id;
                $requestIds[] = (int) $request->id;
            }

            $itemSourceRequestIds[$itemKey] = $this->sortedUnique($ids);
        }

        $requestIds = $this->sortedUnique($requestIds);
        $sessionIds = $this->sortedUnique($sessionIds);

        // the provisional claim identity of EVERY union request, read before
        // the locks: source session, anchor, stored span, complete landing set
        $provisionalClaims = [];

        if ($requestIds !== []) {
            /** @var array<int, ReplacementRequest> $requestRows */
            $requestRows = ReplacementRequest::query()
                ->whereIn('id', $requestIds)
                ->get()
                ->keyBy('id')
                ->all();

            foreach ($requestRows as $request) {
                $sessionIds[] = (int) $request->class_session_id;
                $provisionalClaims[(int) $request->id] = $this->claimSnapshot($request);
            }

            $sessionIds = $this->sortedUnique($sessionIds);
        }

        // ── the D4 lock order, everything ascending ──
        $this->writeLock->lockWithinTransaction($sessionIds);

        ClassSession::query()
            ->whereIn('id', $sessionIds)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        if ($requestIds !== []) {
            ReplacementRequest::query()
                ->whereIn('id', $requestIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
        }

        // ── fresh claim re-derivation UNDER the locks, BEFORE the slots ──
        // every union request's identity must still match its provisional
        // capture EXACTLY — the locks were provisioned for that claim
        $slotIds = [];

        foreach ($provisionalPlans as $plan) {
            $slotIds = array_merge($slotIds, $plan->candidateSlotIds);
        }

        if ($requestIds !== []) {
            /** @var array<int, ReplacementRequest> $freshRequests */
            $freshRequests = ReplacementRequest::query()
                ->whereIn('id', $requestIds)
                ->get()
                ->keyBy('id')
                ->all();

            foreach ($requestIds as $requestId) {
                $fresh = $freshRequests[$requestId] ?? null;

                if ($fresh === null) {
                    // a union request decided or vanished between the estimate
                    // and the lock — the union no longer describes the truth
                    throw new OriginalOccurrenceReleaseException('membership_drift');
                }

                $freshClaim = $this->claimSnapshot($fresh);
                $before = $provisionalClaims[$requestId];

                if ($freshClaim['source'] !== $before['source']
                    || $freshClaim['anchorId'] !== $before['anchorId']
                    || $freshClaim['span'] !== $before['span']
                    || $freshClaim['landing'] !== $before['landing']) {
                    throw new OriginalOccurrenceReleaseException('membership_drift');
                }

                $slotIds = array_merge($slotIds, $freshClaim['landing']);
            }
        }

        $slotIds = $this->sortedUnique($slotIds);

        if ($slotIds !== []) {
            TimeSlot::query()
                ->whereIn('id', $slotIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
        }

        // ── fresh re-read under the locks ──
        $freshSessions = ClassSession::query()
            ->whereIn('id', array_map(fn (array $item): int => (int) $item['session']->id, $items))
            ->get()
            ->keyBy('id');

        $plans = [];

        foreach ($items as $item) {
            $week = (int) $item['week'];
            $session = $freshSessions->get((int) $item['session']->id);

            if ($session === null) {
                // the source session vanished under us — membership drift
                throw new OriginalOccurrenceReleaseException('membership_drift');
            }

            $fresh = $this->planner->plan($session, $week);
            $freshSourceRequestIds = $this->sortedUnique(array_values(array_map(
                fn (ReplacementRequest $request): int => (int) $request->id,
                $this->activeSourceRequests((int) $session->id, $week),
            )));

            // membership law: the fresh plan may only read what this
            // transaction already holds — anything else drifted in after the
            // provisional discovery, and the locks must not grow out of order
            if (! $this->subset($fresh->candidateSlotIds, $slotIds)
                || ! $this->subset($fresh->claimingRequestIds, $requestIds)) {
                throw new OriginalOccurrenceReleaseException('membership_drift');
            }

            // the occurrence's attached-request membership is UNCHANGED, not
            // merely a subset — a request decided or added under the locks is
            // drift, refused before any write
            if ($freshSourceRequestIds !== ($itemSourceRequestIds[$this->key((int) $session->id, $week)] ?? [])) {
                throw new OriginalOccurrenceReleaseException('membership_drift');
            }

            $plans[$this->key((int) $session->id, $week)] = $fresh;
        }

        return $plans;
    }

    /**
     * ONE union request's claim identity: its source session, anchor id,
     * stored span and the COMPLETE landing set the shared claim validation
     * derives (the junction ids, or the anchor plus every row at the mapped
     * span's coordinates). SELECT-only; recomputed fresh after the request
     * locks — compared for an EXACT match, never a subset.
     *
     * @return array{source: int, anchorId: int, span: int|null, landing: list<int>}
     */
    private function claimSnapshot(ReplacementRequest $request): array
    {
        return [
            'source' => (int) $request->class_session_id,
            'anchorId' => (int) $request->replacement_time_slot_id,
            'span' => $request->replacement_span,
            'landing' => $this->planner->validateRequestClaim($request)->landingSlotIds,
        ];
    }

    /**
     * The occurrence's active (pending/approved) source requests.
     *
     * @return array<int, ReplacementRequest>
     */
    private function activeSourceRequests(int $sessionId, int $week): array
    {
        return ReplacementRequest::query()
            ->where('class_session_id', $sessionId)
            ->where('week_number', $week)
            ->whereIn('status', ['pending', 'approved'])
            ->get()
            ->keyBy('id')
            ->all();
    }

    /**
     * Whether every id of $candidate was already part of $held — the
     * membership-drift test (no id may join the lock union after the fact).
     *
     * @param  list<int>  $candidate
     * @param  list<int>  $held
     */
    private function subset(array $candidate, array $held): bool
    {
        return $this->sortedUnique($candidate) === array_values(array_intersect($this->sortedUnique($candidate), $held));
    }

    /**
     * @param  list<int>  $ids
     * @return list<int>
     */
    private function sortedUnique(array $ids): array
    {
        $unique = array_values(array_unique(array_map('intval', $ids)));
        sort($unique);

        return $unique;
    }

    private function key(int $sessionId, int $week): string
    {
        return $sessionId.':'.$week;
    }
}
