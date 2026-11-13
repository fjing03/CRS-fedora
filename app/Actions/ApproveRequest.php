<?php

namespace App\Actions;

use App\Exceptions\OccurrenceWriteLockContended;
use App\Mail\ReplacementApprovalNotice;
use App\Mail\ReplacementDecided;
use App\Models\AuditLog;
use App\Models\ClassException;
use App\Models\ReplacementRequest;
use App\Models\Semester;
use App\Models\TimeSlot;
use App\Models\User;
use App\Services\ApprovalValidity;
use App\Services\OccurrenceMutationLocks;
use App\Services\OriginalOccurrenceEntry;
use App\Services\OriginalOccurrencePlan;
use App\Services\OriginalOccurrenceReservation;
use App\Services\ReplacementRequestMapper;
use App\Services\RequestClaimEvidence;
use App\Support\LocalTime;
use App\Support\Notifications\NotificationEmitter;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use RuntimeException;

/**
 * PL decision — APPROVE a pending replacement request (SDD
 * span-aware-approval-flow, design D1 + spec decision-flow R1; grill Q13: no
 * ownership guard — a PL may decide any pending request).
 *
 * The decision txn (verbatim design D1 + cancelled-slot-reuse D4, task A08):
 * STEP 0 — the D4 occurrence locks (transaction-level advisory locks on the
 * SOURCE class session, then the source/claiming/extra request rows, then the
 * union of original + landing time_slots ascending — the helper re-plans the
 * original occurrence AFTER the locks and refuses contention/drift BEFORE any
 * write) → the request-row lock → the CANONICAL request-claim proof
 * (`OriginalOccurrenceReservation::validateRequestClaim()`, inspected
 * provisionally before the txn and FRESH under the locks: the FULL
 * junction-or-fallback span, its stored-width completeness included — a
 * true legacy null width maps to ONE cell; a refused geometry/state answers
 * the existing named-time loser copy BEFORE any write) → the
 * original-occurrence gate (a missing/ambiguous original refuses the
 * WHOLE decision before any landing flip) → flip ALL proven cells
 * version+state-checked (throw-out-of-txn on any mismatch — NOTHING stays
 * flipped; existing junction rows and the legacy absence stay untouched —
 * no fabricated links) → the approve stamp → ONE summary audit
 * (`action='approved'`, LEGAL token; `approver_note` rides the details ONLY
 * when non-blank — D13) → one per-slot flip audit → the original-exception
 * stamp ONLY when the pair has none (a user's cancellation reason —
 * `official_event` included — is never replaced, spec S11) → the original
 * release through the SHARED `ReleaseOriginalOccurrenceSlots` with the
 * LOCKED PRE-FLIP plan (the approving request's own landing cells — the
 * junction receipt OR the whole anchor/span fallback, stale
 * `class_session_id`s included — are receipt no-ops, so a same-range
 * rebook is never freed by its own approval) → mails queued AFTER commit
 * (never inside the OCC window).
 *
 * Losers return `['ok' => false, 'conflict_reason' => …]` with the EXISTING
 * family strings ('already decided' / the `slot_not_available` named-time
 * copy) — no new token names (Q5). Lock contention answers the queue's
 * refresh/retry trigger ('already decided'); every thrown refusal rides out
 * of a fully rolled-back transaction — no partial decision ever survives.
 */
final class ApproveRequest
{
    /**
     * @return array{ok: bool, conflict_reason: string|null}
     */
    public function __invoke(User $decider, int $requestId, ?string $approverNote = null): array
    {
        $request = ReplacementRequest::query()->find($requestId);

        // A stale page's id either moved on or is gone — case (a).
        if ($request === null) {
            return $this->loser(ApprovalValidity::ALREADY_DECIDED);
        }

        /* A08 (design D4): the ORIGINAL source class session drives the helper's
           occurrence locks and the release plan — resolved BEFORE the txn (a
           read-only lookup); the helper re-locks the row inside. */
        $sourceSession = $request->classSession;
        $originalWeek = (int) $request->week_number;

        $anchorSlot = TimeSlot::find((int) $request->replacement_time_slot_id);

        /* class_session_id is NOT NULL with an FK: a source session that
           cannot resolve is unreadable state, never an approvable orphan —
           fail closed instead of approving without the source locks. */
        if ($sourceSession === null) {
            return $this->loser($this->refusalCopy(null, $anchorSlot));
        }

        /** Setup-time case (b): a locked-and-drifted span before the txn. */
        $validity = app(ApprovalValidity::class)->evaluate($request);
        if (! $validity->valid) {
            return $this->loser($validity->reason);
        }

        /* A08 blocker fix — the PROVISIONAL canonical claim: the FULL
           junction-or-fallback geometry/state proof (spanFromAnchor maps a
           true legacy null width to ONE cell). A refused claim answers the
           existing named-time loser copy immediately: no txn, no write. */
        $reservation = app(OriginalOccurrenceReservation::class);
        $provisional = $reservation->validateRequestClaim($request);

        if (! $provisional->isProven()) {
            return $this->loser($this->claimRefusalCopy($provisional, $anchorSlot));
        }

        try {
            DB::transaction(function () use ($request, $decider, $approverNote, $reservation, $provisional, $sourceSession, $originalWeek, $anchorSlot): void {
                /* Step 0 — the D4 occurrence locks (cancelled-slot-reuse A08):
                   transaction-level advisory locks on the SOURCE class session,
                   then the source/claiming/extra request rows, then the union
                   of original + landing time_slots in ascending order. The
                   helper re-plans the original occurrence AFTER the locks and
                   throws on contention (OccurrenceWriteLockContended) or
                   membership drift (OriginalOccurrenceReleaseException) before
                   any write; the returned plan is the LOCKED PRE-FLIP plan the
                   release below consumes. */
                $plans = app(OccurrenceMutationLocks::class)->lock(
                    [['session' => $sourceSession, 'week' => $originalWeek]],
                    [(int) $request->id],
                );
                $plan = $plans[((int) $sourceSession->id).':'.$originalWeek] ?? null;

                if (! $plan instanceof OriginalOccurrencePlan) {
                    // The helper's fixed key contract was not honoured —
                    // treat it as drift and refuse without any write.
                    throw new SpanDriftException($this->refusalCopy(null, $anchorSlot));
                }

                // Step 1 — request-row lock (held for the WHOLE txn).
                $locked = ReplacementRequest::query()->whereKey($request->id)->lockForUpdate()->first();

                if ($locked === null || (string) $locked->status !== 'pending') {
                    throw new StaleDecisionException(ApprovalValidity::ALREADY_DECIDED);
                }

                /* The locked row must still be the claim the Step 0 lock union
                   was built from: class_session_id/semester_id/week_number
                   selected the source occurrence, replacement_time_slot_id and
                   replacement_span selected the anchor and the block width.
                   Drift on any of them is refused BEFORE the membership
                   compare — the locks were provisioned for the provisional
                   snapshot's claim, not this row's. */
                $provisionalSpan = $request->replacement_span === null ? null : (int) $request->replacement_span;
                $lockedSpan = $locked->replacement_span === null ? null : (int) $locked->replacement_span;

                if ((int) $locked->class_session_id !== (int) $request->class_session_id
                    || (int) $locked->semester_id !== (int) $request->semester_id
                    || (int) $locked->week_number !== (int) $request->week_number
                    || (int) $locked->replacement_time_slot_id !== (int) $request->replacement_time_slot_id
                    || $lockedSpan !== $provisionalSpan
                ) {
                    throw new SpanDriftException($this->refusalCopy(null, $anchorSlot));
                }

                /* Step 1b — the FRESH canonical claim under the locks
                   (replaces the junction-membership compare and the old
                   full-span near-copy): geometry, one-run, anchor-opens-run,
                   member presence and the per-cell claim state in ONE
                   validation. A refused claim is drift — the named-time
                   refusal BEFORE any write — and the proven set must equal
                   the provisional one EXACTLY (kind, membership, landing);
                   the helper's union already holds every landing row's lock,
                   so no lock is acquired out of order (design D4). */
                $live = $reservation->validateRequestClaim($locked);

                if (! $live->isProven()) {
                    throw new SpanDriftException($this->claimRefusalCopy($live, $anchorSlot));
                }

                if ($live->kind !== $provisional->kind
                    || $live->provenSlotIds() !== $provisional->provenSlotIds()
                    || $live->landingSlotIds !== $provisional->landingSlotIds) {
                    throw new SpanDriftException($this->refusalCopy(null, $anchorSlot));
                }

                /* Step 1b.1 — the proven set must cover the request's OWN
                   declared width (junction: its stored span; fallback: the
                   mapped run — legacy null = one cell). A narrower proof is
                   a missing tail coordinate: refuse at its own named time,
                   never approve a partial block. */
                $this->assertClaimCoversTheStoredWidth($locked, $live, $anchorSlot);

                /* Step 1c — the original-occurrence gate BEFORE any landing
                   flip (design D4): a missing or ambiguous original cell
                   refuses the whole decision — no partial release, no partial
                   decision (spec S11; the strict frozen rule). */
                if ($plan->hasRefusal()) {
                    throw new SpanDriftException($this->refusalCopy($plan->firstRefusingEntry(), $anchorSlot));
                }

                // Step 2 — flip ALL proven cells (version+state-checked).
                // The helper's union already holds every landing row's lock
                // (proven ⊆ landing), the cells were read FRESH under those
                // locks and are ordered ascending by id — no new lock is
                // acquired out of order, and the conditional predicate binds
                // BOTH the version and the claim state.
                $provenIds = $live->provenSlotIds();

                foreach ($live->cells as $cell) {
                    $affected = DB::update(
                        'UPDATE time_slots SET status = ?, version = ? WHERE id = ? AND version = ? AND status = ?',
                        ['occupied', $cell['version'] + 1, $cell['slot_id'], $cell['version'], 'pending'],
                    );

                    if ($affected === 0) {
                        throw new SpanDriftException(
                            ApprovalValidity::notAvailableMessage(
                                (string) $cell['start_time'],
                                (string) $cell['end_time'],
                            ),
                        );
                    }
                }

                // Step 5 — the junction rows STAY (the LIVE receipt); the
                // legacy anchor-only absence stays too — no fabricated links.

                // Step 6 — the approve stamp.
                $locked->fill([
                    'status' => 'approved',
                    'approver_id' => $decider->id,
                    'decided_at' => now(),
                ]);
                $locked->save();

                // Step 7 — one per-slot flip audit each (the FULL proven span).
                foreach ($provenIds as $slotId) {
                    AuditLog::create([
                        'user_id' => $decider->id,
                        'action' => 'approved',
                        'replacement_request_id' => $locked->id,
                        'time_slot_id' => (int) $slotId,
                        'old_status' => 'pending',
                        'new_status' => 'occupied',
                        'details' => [
                            'decision' => 'approved',
                        ],
                    ]);
                }

                // The summary details live UP FRONT (the release rider below
                // mutates them); the summary audit row writes after.
                $details = [
                    'decision' => 'approved',
                    'slots' => $provenIds,
                ];
                if ($approverNote !== null && trim($approverNote) !== '') {
                    $details['approver_note'] = trim((string) $approverNote);
                }

                // Release (design D3/D4) inside the same txn BEFORE the summary
                // audit so its rider lands in the row: the exception stamps
                // ONLY when the pair has none (a user's cancellation reason —
                // official_event included — is never replaced), and the
                // original cells go through the SHARED release action with the
                // locked pre-flip plan — request-held landing cells (the
                // approving request's own, stale ids included) are receipt
                // no-ops, never freed by their own approval.
                $released = $this->releaseOriginalThroughPlan($locked, $plan, $details);

                // Step 7 — the summary audit (action='approved', LEGAL token).
                AuditLog::create([
                    'user_id' => $decider->id,
                    'action' => 'approved',
                    'replacement_request_id' => $locked->id,
                    'time_slot_id' => (int) $locked->replacement_time_slot_id,
                    'old_status' => 'pending',
                    'new_status' => 'occupied',
                    'occ_validation_result' => 'success',
                    'details' => $details,
                ]);

                // Step 10 — mails AFTER COMMIT (never inside the OCC window).
                DB::afterCommit(function () use ($locked, $decider, $details, $released): void {
                    $this->queueDecidedMail($locked, $decider->name, $details);
                    $this->queueCohortMail($locked, $released);
                });
                /* SDD notifications-real-bridge: the island rows ride the SAME
                   after-commit seam. R3 (notif-recipient-rulings, 2026-11-03)
                   merges the owner's copy into ONE `Replacement class updated`
                   row pointing at the timetable, then every cohort student's
                   notice runs unchanged (the per-role link law). The owner row
                   is written ONCE, BEFORE the loop — inside it, it would repeat
                   once per cohort. */
                DB::afterCommit(function () use ($locked, $decider): void {
                    $session = $locked->classSession;
                    if ($session === null) {
                        return;
                    }
                    $proposer = $locked->proposer;
                    if ($proposer !== null) {
                        app(NotificationEmitter::class)->replacementConfirmed(
                            $proposer,
                            $session,
                            (int) $locked->id,
                            (int) $locked->week_number,
                            (string) $decider->name,
                        );
                    }
                    foreach ($session->cohorts as $cohort) {
                        app(NotificationEmitter::class)->approvalNotice(
                            $session,
                            $cohort->students()->with('user')->get(),
                            (int) $locked->id,
                            (int) $locked->week_number,
                        );
                    }
                });
            });
        } catch (StaleDecisionException $e) {
            return $this->loser($e->reason);
        } catch (SpanDriftException $e) {
            return $this->loser($e->reason);
        } catch (OccurrenceWriteLockContended) {
            /* D4: contention refuses with the queue's refresh/retry trigger —
               the EXISTING 'already decided' family string (grill Q5). The
               transaction rolled back before any write; nothing is queued. */
            return $this->loser(ApprovalValidity::ALREADY_DECIDED);
        } catch (OriginalOccurrenceReleaseException $e) {
            /* The helper's own drift revalidation or the release's stale
               member: mapped onto the existing named-time family (Q5). The
               transaction already rolled back — no partial decision. */
            return $this->loser($this->refusalCopy($e->entry, $anchorSlot));
        }

        return ['ok' => true, 'conflict_reason' => null];
    }

    /**
     * A08 (design D3/D4) — the exception stamp + the original release through
     * the SHARED release action, driven by the LOCKED PRE-FLIP plan the helper
     * produced inside this transaction.
     *
     * The stamp fires only when the (source session, original week) pair has
     * NO exception yet: a user's cancellation reason — `official_event`
     * included — is never replaced (spec S11). The release action consumes the
     * plan's `original` subset under complete identity/owner/version
     * conditions and refuses atomically on any drift; `available` and
     * `request_held` cells ride the receipt as no-ops, so the approving
     * request's own landing cells — exact or partial overlaps, stale
     * `class_session_id` included — are never freed by their own approval.
     * The rider's `out_of_range` list reports rows carrying the source id
     * outside the occurrence's coordinates (reported, never freed — D3).
     *
     * @param  array<string, mixed>  $details  (mutates: the release rider)
     * @return array<int, int> the freed slot ids (receipt order, slot id ascending)
     */
    private function releaseOriginalThroughPlan(ReplacementRequest $locked, OriginalOccurrencePlan $plan, array &$details): array
    {
        $exceptionExisted = ClassException::query()
            ->where('class_session_id', (int) $locked->class_session_id)
            ->where('week_number', $plan->weekNumber)
            ->exists();

        $stamped = false;

        if (! $exceptionExisted) {
            ClassException::create([
                'class_session_id' => (int) $locked->class_session_id,
                'week_number' => $plan->weekNumber,
                'reason' => 'official_event',
            ]);
            $stamped = true;
        }

        $receipt = app(ReleaseOriginalOccurrenceSlots::class)($plan);

        $freed = array_map(
            static fn (array $cell): int => (int) $cell['slot_id'],
            $receipt->changed,
        );

        $details['original_release'] = [
            'class_session_id' => (int) $locked->class_session_id,
            'week_number' => $plan->weekNumber,
            'exception_stamped' => true,
            'exception_created_here' => $stamped,
            'freed_slots' => $freed,
            'cells' => $receipt->changed,
            'no_ops' => $receipt->noOps,
            'out_of_range' => $plan->outOfRange,
        ];

        return $freed;
    }

    /**
     * A refusal mapped onto the EXISTING named-time loser family (grill Q5 —
     * no new token names): a refusing CELL names its own original
     * coordinates; a plan-level refusal (malformed range, invalid week), a
     * missing plan key and a junction-membership drift fall back to the
     * request's own anchor times — the queue's refresh/retry copy.
     */
    private function refusalCopy(?OriginalOccurrenceEntry $entry, ?TimeSlot $anchor): string
    {
        if ($entry !== null) {
            return ApprovalValidity::notAvailableMessage($entry->startTime, $entry->endTime);
        }

        return ApprovalValidity::notAvailableMessage(
            $anchor !== null ? (string) $anchor->start_time : '',
            $anchor !== null ? (string) $anchor->end_time : '',
        );
    }

    /**
     * A refused canonical claim maps onto the EXISTING named-time loser
     * family (grill Q5 — no new token names): the refusing cell names its
     * own coordinates; a refusal without a readable cell (an orphan link, an
     * inactive claim) falls back to the request's own anchor times.
     */
    private function claimRefusalCopy(RequestClaimEvidence $evidence, ?TimeSlot $anchor): string
    {
        $row = $evidence->refusalSlotId !== null ? TimeSlot::find($evidence->refusalSlotId) : null;

        if ($row !== null) {
            return ApprovalValidity::notAvailableMessage((string) $row->start_time, (string) $row->end_time);
        }

        return $this->refusalCopy(null, $anchor);
    }

    /**
     * The proven set must cover the request's OWN declared width: the
     * junction receipt its stored span when one is stored, the fallback run
     * the mapped width (a true legacy null width maps to ONE cell). A
     * narrower proof is a missing tail coordinate: refuse at its own named
     * time — never approve a partial block.
     */
    private function assertClaimCoversTheStoredWidth(ReplacementRequest $locked, RequestClaimEvidence $live, ?TimeSlot $anchor): void
    {
        if ($live->kind === 'junction') {
            $span = $locked->replacement_span;

            if ($span !== null && count($live->provenSlotIds()) !== (int) $span) {
                $cells = $live->cells;
                usort($cells, fn (array $a, array $b): int => strcmp((string) $a['start_time'], (string) $b['start_time']));
                $first = $cells[0] ?? null;

                throw new SpanDriftException($first !== null
                    ? ApprovalValidity::notAvailableMessage((string) $first['start_time'], (string) $first['end_time'])
                    : $this->refusalCopy(null, $anchor));
            }

            return;
        }

        $mapped = app(ReplacementRequestMapper::class)
            ->spanFromAnchor($locked->replacementTimeSlot, $locked->replacement_span);

        if ($mapped === null) {
            throw new SpanDriftException($this->refusalCopy(null, $anchor));
        }

        $provenStarts = [];

        foreach ($live->cells as $cell) {
            $provenStarts[(string) $cell['start_time']] = true;
        }

        $runStart = Carbon::parse((string) $mapped['start']);

        for ($k = 0; $k < (int) $mapped['count']; $k++) {
            $coordinate = $runStart->copy()->addMinutes($k * 30);

            if (! isset($provenStarts[$coordinate->format('H:i:s')])) {
                throw new SpanDriftException(ApprovalValidity::notAvailableMessage(
                    $coordinate->format('H:i:s'),
                    $coordinate->copy()->addMinutes(30)->format('H:i:s'),
                ));
            }
        }
    }

    /**
     * @param  array<string, mixed>  $details
     */
    private function queueDecidedMail(ReplacementRequest $locked, string $deciderName, array $details): void
    {
        $proposer = $locked->proposer;

        if ($proposer === null) {
            return; // orphaned row — nothing to notify
        }

        // CAPTURE-AT-QUEUE-TIME (spec notifications R1#2): span/venue/reason
        // are resolved NOW, pre-release — a rejected row's junction is gone
        // by the time a worker renders.
        $mapper = app(ReplacementRequestMapper::class);
        $span = $mapper->replacementStrings($locked, LocalTime::today());

        /* audit #29: the email reads the date too — `Mon, 9 Nov 2026, 1:30 PM ~ 3:30 PM`
           — while the page payload keeps its ISO date + 24-hour span. */
        $mailSpan = $mapper->span($locked);
        $semester = Semester::find((int) ($locked->classSession?->semester_id));
        $schedule = $mailSpan !== null && $semester !== null
            ? ($mapper->mailSchedule($mailSpan, Carbon::parse((string) $semester->start_date)) ?? ($span['time'] ?? ''))
            : ($span['time'] ?? '');

        Mail::to((string) $proposer->email)->queue(new ReplacementDecided(
            (int) $locked->id,
            (string) ($locked->classSession->module->module_code ?? ''),
            'approved',
            $schedule,
            $span['venue'] ?? '',
            null,
            $deciderName,
        ));
    }

    /**
     * @param  array<int, int>  $freedSlots
     */
    private function queueCohortMail(ReplacementRequest $locked, array $freedSlots): void
    {
        $session = $locked->classSession;

        if ($session === null) {
            return;
        }

        $mapper = app(ReplacementRequestMapper::class);
        $span = $mapper->replacementStrings($locked, LocalTime::today());

        $mailSpan = $mapper->span($locked);
        $semester = Semester::find((int) ($session->semester_id));
        $mailDate = $mailSpan !== null && $semester !== null
            ? $mapper->mailDate($mailSpan, Carbon::parse((string) $semester->start_date))
            : ($span['date'] ?? '');
        $mailTime = $mailSpan !== null ? $mapper->mailTime($mailSpan) : ($span['time'] ?? '');

        foreach ($session->cohorts as $cohort) {
            DB::afterCommit(function () use ($cohort, $locked, $span, $mailDate, $mailTime): void {
                $students = $cohort->students()->with('user')->get();

                foreach ($students as $student) {
                    $user = $student->user;

                    if ($user !== null && $user->email !== '') {
                        Mail::to((string) $user->email)->queue(new ReplacementApprovalNotice(
                            (int) $locked->id,
                            (string) ($locked->classSession->module->module_code ?? ''),
                            (string) ($cohort->programme->programme_code ?? ''),
                            $mailDate,
                            $mailTime,
                            $span['venue'] ?? '',
                        ));
                    }
                }
            });
        }
    }

    /** @return array{ok: false, conflict_reason: string} */
    private function loser(string $reason): array
    {
        return ['ok' => false, 'conflict_reason' => $reason];
    }
}

/** Case (a): the request row moved (status ≠ pending). Rides its own text. */
final class StaleDecisionException extends RuntimeException
{
    public function __construct(public readonly string $reason) {}
}

/** Case (b): the span drifted (member missing / status ≠ pending / version moved). */
final class SpanDriftException extends RuntimeException
{
    public function __construct(public readonly string $reason) {}
}
