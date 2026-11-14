<?php

namespace App\Actions;

use App\Exceptions\OccurrenceWriteLockContended;
use App\Models\AuditLog;
use App\Models\ClassException;
use App\Models\ClassSession;
use App\Models\Holiday;
use App\Models\ReplacementRequest;
use App\Models\ReplacementRequestSlot;
use App\Models\Semester;
use App\Models\TimeSlot;
use App\Models\User;
use App\Models\Venue;
use App\Services\OccurrenceMutationLocks;
use App\Services\OriginalOccurrencePlan;
use App\Services\OriginalOccurrenceReservation;
use App\Services\ReplacementRequestMapper;
use App\Support\CohortLabel;
use App\Support\LocalTime;
use App\Support\Notifications\NotificationEmitter;
use App\Support\WorkingHours;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Cancel a SET of class occurrences as ONE decision (SDD cancel-whole-day, T2;
 * SDD cancelled-slot-reuse A05/A06 — original-slot release integrated).
 *
 * The user's rule (2026-11-11): a lecturer who is away for a day cancels the day,
 * not four separate classes. Because that one action can also withdraw requests
 * students were waiting on, it is deliberately strict:
 *
 *   · every item is re-checked BEFORE anything is written — owner, the 1–14 week
 *     range, the 2-working-day window, the holiday/Sunday ban, the reason, and
 *     "no exception for this occurrence yet" — first cheaply, then AGAIN under
 *     the locks, so the locked truth is what is cancelled;
 *   · ONE transaction: the complete mutation union (advisory locks, source
 *     sessions, attached requests and the original + landing slot union) is
 *     locked FIRST through {@see OccurrenceMutationLocks}, in D4 order;
 *   · EVERY selected occurrence's original cells are classified BEFORE any
 *     flip or closure — a missing or ambiguous cell refuses the WHOLE batch;
 *   · a pending or approved request on a cancelled occurrence is CLOSED in the
 *     same transaction (status `cancelled`, `decided_at`) and ONLY its proven
 *     landing cells are freed through `ReleaseRequestSlots`' explicit
 *     cancellation-only policy — a newer holder is never released; the flips
 *     ride a truthful `attached_release` receipt inside the `class_cancelled`
 *     audit (junction ids captured before the delete, per cell the ACTUAL old
 *     state/version/owner → available, owner pointer cleared);
 *   · the original cells are released through `ReleaseOriginalOccurrenceSlots`
 *     with the locked, freshly re-planned plan; the receipt rides the existing
 *     legal `class_cancelled` audit (`original_release`) — no new audit kind;
 *   · one audit row per cancelled class;
 *   · notifications AFTER the OUTERMOST commit, one fan-out per class — a
 *     rollback sends none;
 *   · SDD approved-replacement-cancellation (D1–D3): an optional
 *     `$approvedTargets` list (request_id + LANDING week/day) lets the same
 *     batch cancel an OWNED APPROVED replacement. The union lock covers the
 *     request (and through it its source session and landing cells) as EXTRA
 *     requests — an approved target is never planned or released as an
 *     original occurrence and never refused for a duplicate exception. The
 *     proven landing booking is freed through the strict attached policy, the
 *     request becomes `cancelled`, and the audit stays `cancelled` (never
 *     `class_cancelled` — the source history is immutable). The landing
 *     window/holiday/Sunday gates are ALWAYS enforced on the server, single
 *     cancellation included, on the ACTUAL landing date.
 */
final class CancelClassBatch
{
    public function __construct(
        private readonly ReplacementRequestMapper $mapper = new ReplacementRequestMapper,
        private readonly OriginalOccurrenceReservation $planner = new OriginalOccurrenceReservation,
    ) {}

    /**
     * @param  list<array{session: ClassSession, week: int}>  $items  the recurring occurrences (an approved-only batch passes NONE)
     * @param  list<array{request_id: int, week: int, day: int}>  $approvedTargets  OWNED APPROVED replacements; week/day are the LANDING coordinates
     * @return array{ok: bool, refused: string|null, hint: string|null, cancelled: array<int, array<string, mixed>>, requestsClosed: int}
     */
    public function __invoke(
        User $user,
        array $items,
        string $reason,
        ?string $otherDetail = null,
        bool $enforceWindow = true,
        array $approvedTargets = [],
    ): array {
        if ($items === [] && $approvedTargets === []) {
            return $this->refuse('empty', 'Nothing was selected to cancel.');
        }

        if (! CancelClass::validReason($reason)) {
            return $this->refuse('invalid_reason', 'Please select a cancellation reason.');
        }

        if ($reason === 'other' && trim((string) $otherDetail) === '') {
            return $this->refuse('invalid_reason', 'Please describe the reason for “Other”.');
        }

        // D1/D2 — the approved targets resolve and preflight BEFORE anything is
        // locked; the transaction re-validates every one of them fresh under
        // the locks, so this pass is never authority.
        $activeSemester = Semester::active();
        $approved = [];

        if ($approvedTargets !== []) {
            $resolved = $this->resolveApprovedTargets($user, $approvedTargets, $items, $activeSemester);

            if ($resolved['refusal'] !== null) {
                return $resolved['refusal'];
            }

            $approved = $resolved['targets'];
        }

        foreach ($items as $item) {
            $refusal = $this->refusalFor($user, $item['session'], (int) $item['week'], $reason, $enforceWindow);

            if ($refusal !== null) {
                return $refusal;
            }
        }

        $cancelled = [];
        $requestsClosed = 0;
        $refusal = null;
        $notifications = [];

        try {
            DB::transaction(function () use ($user, $items, $reason, $otherDetail, $enforceWindow, $approved, &$cancelled, &$requestsClosed, &$refusal, &$notifications): void {
                // A05 — the complete union FIRST, then everything re-read under it.
                // The approved targets' requests (and through them their source
                // sessions and landing cells) join the union as EXTRA requests —
                // an approved target is never planned as a recurring item.
                $approvedRequestIds = array_map(fn (array $target): int => (int) $target['request']->id, $approved);
                $plans = app(OccurrenceMutationLocks::class)->lock($items, $approvedRequestIds);

                $freshSessions = ClassSession::query()
                    ->with(['module', 'venue', 'cohorts.programme'])
                    ->whereIn('id', array_map(fn (array $item): int => (int) $item['session']->id, $items))
                    ->get()
                    ->keyBy('id');

                // Re-validate EVERY item under the locks, and classify EVERY
                // occurrence's original cells, before any flip or closure.
                $classified = [];

                foreach ($items as $item) {
                    $session = $freshSessions->get((int) $item['session']->id);
                    $week = (int) $item['week'];

                    if ($session === null) {
                        $refusal = $this->refuse('refresh', 'One of these classes no longer exists — please refresh and try again.');

                        return;
                    }

                    $refusal = $this->refusalFor($user, $session, $week, $reason, $enforceWindow);

                    if ($refusal !== null) {
                        return;
                    }

                    $plan = $plans[$session->id.':'.$week] ?? null;

                    if (! $plan instanceof OriginalOccurrencePlan || $plan->hasRefusal()) {
                        $refusal = $this->refuse('original_release', $this->releaseHint($plan));

                        return;
                    }

                    // the attached request is captured BEFORE any link is
                    // deleted — more than one live claim on one occurrence is
                    // corrupted data the planner may not guess its way out of
                    $attachedRequests = ReplacementRequest::query()
                        ->where('class_session_id', (int) $session->id)
                        ->where('week_number', $week)
                        ->whereIn('status', ['pending', 'approved'])
                        ->orderBy('id')
                        ->lockForUpdate()
                        ->get();

                    if ($attachedRequests->count() > 1) {
                        $refusal = $this->refuse('attached_ambiguous',
                            'This class holds more than one active replacement request for this week — nothing was changed. Please contact the administrator.');

                        return;
                    }

                    $request = $attachedRequests->first();

                    // the same active request is never processed twice: a target
                    // that is BOTH a selected occurrence's attached request and an
                    // approved target is a contradictory selection (D2)
                    if ($request !== null && in_array((int) $request->id, $approvedRequestIds, true)) {
                        $refusal = $this->refuse('overlap',
                            'One of those replacements is also selected as a class to cancel — nothing was changed.');

                        return;
                    }

                    $classified[] = ['session' => $session, 'week' => $week, 'plan' => $plan, 'request' => $request];
                }

                // the approved targets re-validate FRESH under the locks (D2)
                $lockedSemester = Semester::active();
                $approvedToCancel = [];

                if ($approved !== []) {
                    /** @var array<int, ReplacementRequest> $freshApprovedRequests */
                    $freshApprovedRequests = ReplacementRequest::query()
                        ->whereIn('id', $approvedRequestIds)
                        ->get()
                        ->keyBy('id')
                        ->all();

                    $freshApprovedSessions = ClassSession::query()
                        ->with(['module', 'venue', 'cohorts.programme'])
                        ->whereIn('id', array_map(
                            fn (array $target): int => (int) $target['request']->class_session_id,
                            $approved,
                        ))
                        ->get()
                        ->keyBy('id');

                    foreach ($approved as $target) {
                        $freshRequest = $freshApprovedRequests[(int) $target['request']->id] ?? null;
                        $freshSession = $freshApprovedSessions->get((int) $target['request']->class_session_id);

                        if ($freshRequest === null || $freshSession === null) {
                            $refusal = $this->refuse('refresh',
                                'One of those replacements no longer exists — please refresh and try again.');

                            return;
                        }

                        $refusal = $this->approvedRefusalFor(
                            $user,
                            $freshRequest,
                            $freshSession,
                            (int) $target['week'],
                            (int) $target['day'],
                            $lockedSemester,
                        );

                        if ($refusal !== null) {
                            return;
                        }

                        // the display span is captured and proven HERE, under the
                        // locks and BEFORE any write — Phase B may never discover a
                        // missing span after an earlier target has already mutated
                        // (a mid-loop refusal there would commit that target while
                        // claiming nothing changed)
                        $span = $this->mapper->span($freshRequest);

                        if ($span === null) {
                            $refusal = $this->refuse('claim',
                                'One of those replacements\' booking could not be proven safe to release — nothing was changed. Please try again or contact the administrator if this repeats.');

                            return;
                        }

                        $approvedToCancel[] = [
                            'request' => $freshRequest,
                            'session' => $freshSession,
                            'week' => (int) $target['week'],
                            'day' => (int) $target['day'],
                            'span' => $span,
                        ];
                    }
                }

                // Phase B (approved) — the LANDING booking is released and the
                // request closed. No original plan, no original release, no
                // exception and no duplicate-exception check ever touch an
                // approved target: the source history stays exactly as it was.
                foreach ($approvedToCancel as $target) {
                    /** @var ReplacementRequest $request */
                    $request = $target['request'];
                    /** @var ClassSession $session */
                    $session = $target['session'];

                    // the display span was captured and proven in Phase A, under
                    // the locks and BEFORE any write (D3) — facts come from the
                    // validated mapper span, never from posted display strings.
                    // Every approvedToCancel target carries a span, so no check
                    // is needed here: all validation finished before any write.
                    $span = $target['span'];

                    // the junction receipt's ids, captured BEFORE the release
                    // deletes them — the audit must name what it removed
                    $junctionSlotIds = ReplacementRequestSlot::query()
                        ->where('replacement_request_id', (int) $request->id)
                        ->orderBy('time_slot_id')
                        ->pluck('time_slot_id')
                        ->map(fn ($id): int => (int) $id)
                        ->values()
                        ->all();

                    $attachedRelease = [];

                    (new ReleaseRequestSlots)($request, false, function (TimeSlot $slot) use (&$attachedRelease): void {
                        // the model still holds the PRE-flip row, so the receipt
                        // states the truth: the ACTUAL old state and the owner
                        // pointer the flip cleared
                        $attachedRelease[] = [
                            'slot_id' => (int) $slot->id,
                            'week_number' => (int) $slot->week_number,
                            'day_of_week' => (int) $slot->day_of_week,
                            'venue_id' => (int) $slot->venue_id,
                            'start_time' => (string) $slot->start_time,
                            'end_time' => (string) $slot->end_time,
                            'before' => [
                                'status' => (string) $slot->status,
                                'class_session_id' => $slot->class_session_id !== null ? (int) $slot->class_session_id : null,
                                'version' => (int) $slot->version,
                            ],
                            'after' => [
                                'status' => 'available',
                                'class_session_id' => null,
                                'version' => (int) $slot->version + 1,
                            ],
                        ];
                    }, true);

                    // the request keeps its replacement_span, its approver and
                    // every piece of approval evidence — only the state moves
                    $request->fill([
                        'status' => 'cancelled',
                        'decided_at' => now(),
                    ]);
                    $request->save();

                    $details = [
                        'cancelled_by' => 'proposer',
                        'reason' => $reason,
                        'source' => [
                            'class_session_id' => (int) $session->id,
                            'week_number' => (int) $request->week_number,
                        ],
                        'landing' => [
                            'semester_id' => (int) $request->semester_id,
                            'week_number' => (int) $span['week'],
                            'day_of_week' => (int) $span['day'],
                            'venue_id' => (int) $span['venue_id'],
                            'start' => (string) $span['start'],
                            'end' => (string) $span['end'],
                            'replacement_span' => $request->replacement_span,
                        ],
                        'attached_release' => [
                            'request_id' => (int) $request->id,
                            'old_status' => 'approved',
                            'junction_slot_ids' => $junctionSlotIds,
                            'changed' => $attachedRelease,
                        ],
                    ];
                    if ($reason === 'other' && trim((string) $otherDetail) !== '') {
                        $details['other_detail'] = trim((string) $otherDetail);
                    }

                    AuditLog::create([
                        'user_id' => (int) $user->id,
                        'action' => 'cancelled',
                        'replacement_request_id' => (int) $request->id,
                        'time_slot_id' => (int) $request->replacement_time_slot_id,
                        'old_status' => 'approved',
                        'new_status' => null,
                        'details' => $details,
                    ]);

                    $venue = Venue::query()->find((int) $span['venue_id']);

                    // an UNSAVED schedule projection carries the ACTUAL landing
                    // day/time/room to the notifier — it is NEVER persisted (D3)
                    $projection = new ClassSession;
                    $projection->setRawAttributes(array_merge($session->getAttributes(), [
                        'day_of_week' => (int) $span['day'],
                        'start_time' => (string) $span['start'],
                        'end_time' => (string) $span['end'],
                        'venue_id' => (int) $span['venue_id'],
                    ]), true);
                    $projection->exists = false;

                    // the notifier reads relations (module code, cohort labels,
                    // room) — they are set EXPLICITLY from the loaded source
                    // relations and the ACTUAL landing venue, never lazy-loaded
                    // off an unsaved model whose id points at the SOURCE row (D3)
                    $projection->setRelation('module', $session->module);
                    $projection->setRelation('cohorts', $session->cohorts);
                    $projection->setRelation('venue', $venue);

                    $cancelled[] = [
                        'session_id' => (int) $session->id,
                        'week' => (int) $request->week_number, // the SOURCE week (R1)
                        'request_id' => (int) $request->id,
                        'landing_week' => (int) $span['week'],
                        'landing_day' => (int) $span['day'],
                        'start' => substr((string) $span['start'], 0, 5),
                        'end' => substr((string) $span['end'], 0, 5),
                        'code' => (string) ($session->module->module_code ?? ''),
                        'type' => (string) $session->session_type,
                        'venue' => (string) ($venue->room_code ?? ''),
                        'cohorts' => $session->cohorts
                            ->map(fn ($c): string => CohortLabel::spaced($c))
                            ->values()
                            ->all(),
                    ];
                    $requestsClosed++;

                    $notifications[] = [
                        'session' => $projection,
                        'week' => (int) $span['week'],
                        'requestId' => (int) $request->id,
                    ];
                }

                // Phase B — flips and closures, all-or-nothing.
                foreach ($classified as $entry) {
                    /** @var ClassSession $session */
                    $session = $entry['session'];
                    $week = (int) $entry['week'];
                    /** @var OriginalOccurrencePlan $plan */
                    $plan = $entry['plan'];
                    /** @var ReplacementRequest|null $request */
                    $request = $entry['request'];

                    $details = [
                        'class_session_id' => (int) $session->id,
                        'week_number' => $week,
                        'reason' => $reason,
                    ];
                    if ($reason === 'other' && trim((string) $otherDetail) !== '') {
                        $details['other_detail'] = trim((string) $otherDetail);
                    }

                    // the closure first: closing the request deletes its junction rows,
                    // so the plan's classifications were captured above, before this
                    if ($request !== null) {
                        $oldStatus = (string) $request->status;

                        // the junction receipt's ids, captured BEFORE the
                        // release deletes them — the audit must name what it
                        // removed
                        $junctionSlotIds = ReplacementRequestSlot::query()
                            ->where('replacement_request_id', (int) $request->id)
                            ->orderBy('time_slot_id')
                            ->pluck('time_slot_id')
                            ->map(fn ($id): int => (int) $id)
                            ->values()
                            ->all();

                        $attachedRelease = [];

                        (new ReleaseRequestSlots)($request, false, function (TimeSlot $slot) use (&$attachedRelease): void {
                            // the model still holds the PRE-flip row (the flip
                            // is a version-checked raw UPDATE), so the receipt
                            // states the truth: the ACTUAL old state and the
                            // owner pointer the flip cleared
                            $attachedRelease[] = [
                                'slot_id' => (int) $slot->id,
                                'week_number' => (int) $slot->week_number,
                                'day_of_week' => (int) $slot->day_of_week,
                                'venue_id' => (int) $slot->venue_id,
                                'start_time' => (string) $slot->start_time,
                                'end_time' => (string) $slot->end_time,
                                'before' => [
                                    'status' => (string) $slot->status,
                                    'class_session_id' => $slot->class_session_id !== null ? (int) $slot->class_session_id : null,
                                    'version' => (int) $slot->version,
                                ],
                                'after' => [
                                    'status' => 'available',
                                    'class_session_id' => null,
                                    'version' => (int) $slot->version + 1,
                                ],
                            ];
                        }, true);

                        $request->fill([
                            'status' => 'cancelled',
                            'decided_at' => now(),
                        ]);
                        $request->save();

                        AuditLog::create([
                            'user_id' => (int) $user->id,
                            'action' => 'cancelled',
                            'replacement_request_id' => $request->id,
                            'time_slot_id' => (int) $request->replacement_time_slot_id,
                            'old_status' => $oldStatus,
                            'new_status' => null,
                            'details' => ['cancelled_by' => 'class_cancelled', 'reason' => $reason],
                        ]);

                        $details['request_id'] = (int) $request->id;
                        $details['closed_request'] = true;
                        $details['closed_request_status'] = $oldStatus;
                        $details['attached_release'] = [
                            'request_id' => (int) $request->id,
                            'old_status' => $oldStatus,
                            'junction_slot_ids' => $junctionSlotIds,
                            'changed' => $attachedRelease,
                        ];
                        $requestsClosed++;
                    }

                    $exception = ClassException::create([
                        'class_session_id' => (int) $session->id,
                        'week_number' => $week,
                        'reason' => $reason,
                    ]);

                    // the original cells: available/protected cells ride the receipt as no-ops
                    $receipt = (new ReleaseOriginalOccurrenceSlots)($plan);

                    $details['original_release'] = [
                        'class_session_id' => (int) $session->id,
                        'week_number' => $week,
                        'changed' => $receipt->changed,
                        'no_ops' => $receipt->noOps,
                        'out_of_range' => $plan->outOfRange,
                    ];

                    AuditLog::create([
                        'user_id' => (int) $user->id,
                        'action' => 'class_cancelled',
                        'details' => $details,
                    ]);

                    $cancelled[] = [
                        'session_id' => (int) $session->id,
                        'week' => $week,
                        'exception_id' => (int) $exception->id,
                    ] + $this->classDisplay($session);
                }
            });
        } catch (OccurrenceWriteLockContended) {
            return $this->refuse('refresh', 'Someone else is changing these classes right now — please try again.');
        } catch (OriginalOccurrenceReleaseException $e) {
            return $this->refuse('original_release', $this->releaseHint(null, $e->reason));
        } catch (ReleaseDriftException) {
            return $this->refuse('original_release',
                'A replacement block tied to these classes changed while cancelling — nothing was changed. Please try again.');
        }

        if ($refusal !== null) {
            // the batch refused under the locks — nothing was written, so no fan-out
            return $refusal;
        }

        // one fan-out per cancelled class — the existing wording, nothing new;
        // DB::afterCommit fires on the OUTERMOST commit (the day path's outer
        // transaction), so a rolled-back selection notifies nobody.
        // POST-COMMIT FAN-OUT IS BEST-EFFORT: the commit already stands, so a
        // failing emitter must never bubble back up through the day path's
        // catch as a false "Nothing changed" rollback claim — each class's
        // notification failure is logged and the fan-out continues.
        DB::afterCommit(function () use ($user, $items, $notifications): void {
            foreach ($items as $item) {
                try {
                    app(NotificationEmitter::class)->classCancelled($user, $item['session'], (int) $item['week']);
                } catch (\Throwable $e) {
                    report($e);
                }
            }

            // the approved targets' fan-out rides the UNSAVED landing projection
            // + the additive request-id parameter (D3) — best-effort, never a
            // false rollback claim
            foreach ($notifications as $notification) {
                try {
                    app(NotificationEmitter::class)->classCancelled(
                        $user,
                        $notification['session'],
                        $notification['week'],
                        $notification['requestId'],
                    );
                } catch (\Throwable $e) {
                    report($e);
                }
            }
        });

        return [
            'ok' => true,
            'refused' => null,
            'hint' => null,
            'cancelled' => $cancelled,
            'requestsClosed' => $requestsClosed,
        ];
    }

    /**
     * D1/D2 preflight — resolve the submitted approved targets to their OWNED
     * APPROVED requests and source sessions, refusing every contradictory
     * selection before anything is locked. The transaction re-validates each
     * resolved target fresh under the locks; this pass is never authority.
     *
     * @param  list<array{request_id: int, week: int, day: int}>  $approvedTargets
     * @param  list<array{session: ClassSession, week: int}>  $items
     * @return array{targets: list<array{request: ReplacementRequest, session: ClassSession, week: int, day: int}>, refusal: array{ok: false, refused: string, hint: string, cancelled: array<int, array<string, mixed>>, requestsClosed: int}|null}
     */
    private function resolveApprovedTargets(User $user, array $approvedTargets, array $items, ?Semester $activeSemester): array
    {
        $targets = [];
        $seen = [];

        foreach ($approvedTargets as $target) {
            $requestId = (int) $target['request_id'];
            $week = (int) $target['week'];
            $day = (int) $target['day'];

            if (isset($seen[$requestId])) {
                if ($seen[$requestId] !== $week.':'.$day) {
                    return ['targets' => [], 'refusal' => $this->refuse('overlap',
                        'One of those replacements was selected twice with different landing times.')];
                }

                continue; // a harmless repeated entry is normalized
            }
            $seen[$requestId] = $week.':'.$day;

            $request = ReplacementRequest::query()->find($requestId);

            if ($request === null) {
                return ['targets' => [], 'refusal' => $this->refuse('refresh',
                    'One of those replacements no longer exists — please refresh and try again.')];
            }

            $session = ClassSession::query()->find((int) $request->class_session_id);

            if ($session === null) {
                return ['targets' => [], 'refusal' => $this->refuse('refresh',
                    'One of those replacements no longer exists — please refresh and try again.')];
            }

            $refusal = $this->approvedRefusalFor($user, $request, $session, $week, $day, $activeSemester);

            if ($refusal !== null) {
                return ['targets' => [], 'refusal' => $refusal];
            }

            // the same active request is never processed twice: an approved
            // target whose SOURCE occurrence is also a selected normal item
            // would be closed by that cancellation too (D2)
            foreach ($items as $item) {
                if ((int) $item['session']->id === (int) $session->id
                    && (int) $item['week'] === (int) $request->week_number) {
                    return ['targets' => [], 'refusal' => $this->refuse('overlap',
                        'One of those replacements is also selected as a class to cancel — nothing was changed.')];
                }
            }

            $targets[] = ['request' => $request, 'session' => $session, 'week' => $week, 'day' => $day];
        }

        return ['targets' => $targets, 'refusal' => null];
    }

    /**
     * EVERY approved-target gate (D2), usable both as preflight and FRESH under
     * the locks: owner (request + source session), Approved state, active
     * semester (request + source), valid source week, the submitted LANDING
     * coordinate matching the validated mapper span, the FULL proven claim
     * (junction receipt or legacy anchor/span fallback), and the landing date's
     * window/holiday/Sunday gates — the approved path ALWAYS enforces the
     * window on the server, single cancellation included. NO duplicate-exception
     * check ever runs here: the source history of an approved replacement is
     * immutable and must not refuse its landing cancellation.
     *
     * @return array{ok: false, refused: string, hint: string, cancelled: array<int, array<string, mixed>>, requestsClosed: int}|null
     */
    private function approvedRefusalFor(User $user, ReplacementRequest $request, ClassSession $session, int $week, int $day, ?Semester $activeSemester): ?array
    {
        if ((int) $request->proposer_id !== (int) $user->id
            || (int) $session->lecturer_id !== (int) $user->id) {
            return $this->refuse('not_owner', 'One of those replacements is not yours to cancel.');
        }

        if ((string) $request->status !== 'approved') {
            return $this->refuse('not_approved',
                'One of those replacements is no longer approved — nothing was changed.');
        }

        if ((int) $request->class_session_id !== (int) $session->id) {
            return $this->refuse('not_owner', 'One of those replacements does not belong to its class.');
        }

        if ($activeSemester === null
            || (int) $request->semester_id !== (int) $activeSemester->id
            || (int) $session->semester_id !== (int) $activeSemester->id) {
            return $this->refuse('invalid_semester',
                'One of those replacements does not belong to the active semester.');
        }

        $sourceWeek = (int) $request->week_number;

        if ($sourceWeek < 1 || $sourceWeek > 14) {
            return $this->refuse('invalid_week', 'One of those replacements has an invalid source week.');
        }

        $span = $this->mapper->span($request);

        if ($span === null
            || (int) $span['week'] !== $week
            || (int) $span['day'] !== $day) {
            return $this->refuse('wrong_landing',
                'One of those replacements no longer runs at the shown time — please refresh and try again.');
        }

        if (! $this->planner->validateRequestClaim($request)->isProven()) {
            return $this->refuse('claim',
                'One of those replacements\' booking could not be proven safe to release — nothing was changed. Please try again or contact the administrator if this repeats.');
        }

        // the LANDING date's gates — the actual landing week/day, never the
        // (possibly long-past) source occurrence
        $landingDate = $this->classDate($activeSemester, $week, $day);

        if ($landingDate === null) {
            return $this->refuse('invalid_week',
                'One of those replacements does not have a date in this semester.');
        }

        if ($landingDate->lt($this->windowCutoff($activeSemester))) {
            return $this->refuse('too_old', 'One of those replacements is more than 2 working days ago.');
        }

        if ($this->isHoliday((int) $activeSemester->id, $week, $day)) {
            return $this->refuse('holiday', 'One of those replacements falls on a holiday or a Sunday.');
        }

        return null;
    }

    /**
     * The server display facts EVERY receipt carries (D3) — from the models,
     * never from posted display strings.
     *
     * @return array{start: string, end: string, code: string, type: string, venue: string, cohorts: list<string>}
     */
    private function classDisplay(ClassSession $session): array
    {
        return [
            'start' => substr((string) $session->start_time, 0, 5),
            'end' => substr((string) $session->end_time, 0, 5),
            'code' => (string) ($session->module->module_code ?? ''),
            'type' => (string) $session->session_type,
            'venue' => (string) ($session->venue->room_code ?? ''),
            'cohorts' => array_values($session->cohorts
                ->map(fn ($c): string => CohortLabel::spaced($c))
                ->all()),
        ];
    }

    /** @return array{ok: false, refused: string, hint: string, cancelled: array<int, array<string, mixed>>, requestsClosed: int}|null */
    private function refusalFor(User $user, ClassSession $session, int $week, string $reason, bool $enforceWindow): ?array
    {
        if ((int) $session->lecturer_id !== (int) $user->id) {
            return $this->refuse('not_owner', 'One of these classes is not yours to cancel.');
        }

        if ($week < 1 || $week > 14) {
            return $this->refuse('invalid_week', 'That week is outside the semester.');
        }

        if (! CancelClass::validReason($reason)) {
            return $this->refuse('invalid_reason', 'Please select a cancellation reason.');
        }

        $semester = Semester::query()->find((int) $session->semester_id);
        $classDate = $this->classDate($semester, $week, (int) $session->day_of_week);

        if ($classDate === null) {
            return $this->refuse('invalid_week', 'That class does not have a date in this semester.');
        }

        /* The window is the DAY path's guard. The single-class flow never had a
           server-side window (the page has always guarded it), so it passes
           `false` here — keeping `CancelClass`'s shipped behaviour intact
           (spec R7.1). */
        if ($enforceWindow && $classDate->lt($this->windowCutoff($semester))) {
            return $this->refuse('too_old', 'One of these classes is more than 2 working days ago.');
        }

        if ($this->isHoliday((int) $session->semester_id, $week, (int) $session->day_of_week)) {
            return $this->refuse('holiday', 'One of these classes falls on a holiday or a Sunday.');
        }

        $exists = ClassException::query()
            ->where('class_session_id', (int) $session->id)
            ->where('week_number', $week)
            ->exists();

        if ($exists) {
            return $this->refuse('already_cancelled', 'One of these classes is already cancelled.');
        }

        return null;
    }

    /**
     * The helpful copy for an original-release refusal — the plan's own reason
     * when it carries one, else the exception's (`membership_drift` asks for a
     * refresh; stale flips say retry). Never a 500.
     */
    private function releaseHint(?OriginalOccurrencePlan $plan, ?string $exceptionReason = null): string
    {
        $reason = $exceptionReason ?? $plan?->refusalReason() ?? 'unavailable';

        if ($reason === 'membership_drift') {
            return 'These classes changed while you were cancelling — please refresh and try again.';
        }

        return 'One of the selected classes\' original slots could not be released safely ('
            .$reason.'). Nothing was changed — please try again or contact the administrator if this repeats.';
    }

    /** The occurrence's real date: semester start + (week − 1) weeks + the weekday. */
    private function classDate(?Semester $semester, int $week, int $dayOfWeek): ?Carbon
    {
        if ($semester === null || trim((string) $semester->start_date) === '') {
            return null;
        }

        return Carbon::parse((string) $semester->start_date)->startOfDay()
            ->addWeeks($week - 1)
            ->addDays($dayOfWeek);
    }

    /**
     * Midnight of (today − 2 WORKING days) — the server mirror of the page's
     * `ClassCancellation._cutoffDate()` (weekends and semester holidays skipped).
     */
    private function windowCutoff(?Semester $semester): Carbon
    {
        $calendar = new WorkingHours($semester);
        $cursor = LocalTime::today()->startOfDay();
        $counted = 0;

        for ($guard = 0; $guard < 60; $guard++) {
            $cursor = $cursor->copy()->subDay();

            if (! $calendar->isWorkingDay($cursor)) {
                continue;
            }

            if (++$counted >= 2) {
                return $cursor;
            }
        }

        return $cursor;
    }

    private function isHoliday(int $semesterId, int $week, int $dayOfWeek): bool
    {
        return Holiday::query()
            ->where('semester_id', $semesterId)
            ->where('week_number', $week)
            ->where('day_of_week', $dayOfWeek)
            ->exists();
    }

    /** @return array{ok: false, refused: string, hint: string, cancelled: array<int, array<string, mixed>>, requestsClosed: int} */
    private function refuse(string $key, string $hint): array
    {
        return ['ok' => false, 'refused' => $key, 'hint' => $hint, 'cancelled' => [], 'requestsClosed' => 0];
    }
}
