<?php

namespace App\Actions;

use App\Models\ReplacementRequest;
use App\Models\ReplacementRequestSlot;
use App\Models\TimeSlot;
use App\Services\OriginalOccurrenceReservation;
use App\Services\ReplacementRequestMapper;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * The ONE slot-release implementation (SDD cancel-whole-day, task T1;
 * SDD cancelled-slot-reuse A06 — the explicit cancellation-only policy).
 *
 * The three shipped callers (`CancelPendingRequest`, `RejectRequest`,
 * `AutoRejectRequest`) did NOT behave identically, so this helper takes the
 * POLICY as an argument instead of silently picking one:
 *
 *   strict = true   a slot that is missing or no longer `pending`, or a flip that
 *                   loses the version race, THROWS — the caller's transaction
 *                   rolls back and nothing is half-released. (`RejectRequest`.)
 *   strict = false  a drifted slot is SKIPPED: no flip, no audit row. The request
 *                   still closes, exactly as cancel/auto-reject have always done.
 *
 * A06 — `$attachedLanding = true` is the CANCELLATION-ONLY opt-in (accepted
 * only by `CancelClassBatch`): the release set may hold PENDING **or
 * OCCUPIED** cells, so an approved request's landing booking can be freed
 * when its class is cancelled. The policy is strict by nature and refuses
 * the release (throw → caller's rollback) unless the request's FULL claim —
 * geometry AND state — is validated through
 * {@see OriginalOccurrenceReservation::validateRequestClaim()}, the canonical
 * request-claim law: an inactive request, an incoherent receipt run, a
 * midnight-wrapping fallback span or a stale/contradicting cell state all
 * refuse. On top of that, no OTHER active request (junction member or legacy
 * anchor/span fallback via the mapper) may claim a proven cell — a newer
 * holder is never released.
 *
 * Everything else is common and unchanged: the lock is ordered by id, every
 * flip is version-checked, and the junction rows are deleted before this
 * returns.
 *
 * The per-flip audit row is a caller concern (each family writes a different
 * `action`/`details`), so it arrives as a closure and is only called for slots
 * this helper actually freed.
 */
final class ReleaseRequestSlots
{
    public function __construct(
        private readonly ReplacementRequestMapper $mapper = new ReplacementRequestMapper,
        private readonly OriginalOccurrenceReservation $planner = new OriginalOccurrenceReservation,
    ) {}

    /**
     * @param  (callable(TimeSlot): void)|null  $audit  called once per FREED slot
     * @return array<int, int> the freed slot ids, in lock order
     *
     * @throws ReleaseDriftException only when $strict (or $attachedLanding) and a slot did not release
     */
    public function __invoke(ReplacementRequest $request, bool $strict = true, ?callable $audit = null, bool $attachedLanding = false): array
    {
        return $attachedLanding
            ? $this->releaseAttachedLanding($request, $audit)
            : $this->releaseDefault($request, $strict, $audit);
    }

    /**
     * The default policy, byte-identical with the shipped behaviour:
     * junction rows (or the legacy anchor), `pending`-only flips.
     *
     * @return array<int, int>
     */
    private function releaseDefault(ReplacementRequest $request, bool $strict, ?callable $audit): array
    {
        /** @var Collection<int, int> $slotIds */
        $slotIds = ReplacementRequestSlot::query()
            ->where('replacement_request_id', $request->id)
            ->orderBy('time_slot_id')
            ->pluck('time_slot_id')
            ->map(fn ($id): int => (int) $id);

        if ($slotIds->isEmpty()) {
            // legacy anchor-only request — no junction rows to read
            $slotIds = collect([(int) $request->replacement_time_slot_id]);
        }

        $slots = TimeSlot::query()
            ->whereIn('id', $slotIds)
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        if ($strict && $slots->count() !== $slotIds->count()) {
            throw new ReleaseDriftException;
        }

        $freed = [];

        foreach ($slotIds as $slotId) {
            /** @var TimeSlot|null $slot */
            $slot = $slots->get($slotId);

            if ($slot === null) {
                if ($strict) {
                    throw new ReleaseDriftException;
                }

                continue;
            }

            if ((string) $slot->status !== 'pending') {
                if ($strict) {
                    throw new ReleaseDriftException($slot);
                }

                continue; // skip drifted rows: no flip, no false audit row
            }

            $expected = (int) $slot->version;
            $affected = DB::update(
                'UPDATE time_slots SET status = ?, version = ? WHERE id = ? AND version = ?',
                ['available', $expected + 1, $slot->id, $expected],
            );

            if ($affected === 0) {
                if ($strict) {
                    throw new ReleaseDriftException($slot);
                }

                continue; // someone flipped it first — leave their state alone
            }

            $freed[] = (int) $slot->id;

            if ($audit !== null) {
                $audit($slot);
            }
        }

        // The junction rows DELETE — the reservation receipt completes its life.
        $this->deleteReceipt($request);

        return $freed;
    }

    /**
     * The A06 cancellation-only policy: the request's FULL claim — geometry
     * AND state — is validated through the shared planner law BEFORE anything
     * is freed; only the PROVEN cells release, and a newer holder is never
     * released.
     *
     * @return array<int, int>
     */
    private function releaseAttachedLanding(ReplacementRequest $request, ?callable $audit): array
    {
        $evidence = $this->planner->validateRequestClaim($request);

        if (! $evidence->isProven()) {
            // an inactive request, an incoherent run, a midnight-wrapping
            // fallback span or a stale/contradicting cell state — fail closed
            $refused = $evidence->refusalSlotId === null
                ? null
                : TimeSlot::find($evidence->refusalSlotId);

            throw new ReleaseDriftException($refused);
        }

        $slotIds = $evidence->provenSlotIds();

        if ($slotIds === []) {
            throw new ReleaseDriftException; // a claim that proves no cell frees nothing
        }

        $slots = TimeSlot::query()
            ->whereIn('id', $slotIds)
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        if ($slots->count() !== count($slotIds)) {
            throw new ReleaseDriftException;
        }

        $freed = [];
        $expectedState = (string) $request->status === 'pending' ? 'pending' : 'occupied';

        foreach ($slotIds as $slotId) {
            /** @var TimeSlot $slot */
            $slot = $slots->get($slotId);

            if ((string) $slot->status !== $expectedState) {
                // the claim drifted between validation and the row lock —
                // a stale receipt is refused, never flipped to anything
                throw new ReleaseDriftException($slot);
            }

            if ($this->newerHolderExists($request, $slot)) {
                // another live claim also holds this cell — a newer holder is
                // never released
                throw new ReleaseDriftException($slot);
            }

            // the full conditional predicates — coordinate, owner, status and
            // version must still be the locked row the claim proved. A changed
            // cell loses its stale owner pointer too, so a freed booking can
            // never mislead a later reader about its original owner.
            $expected = (int) $slot->version;
            $ownerPredicate = $slot->class_session_id === null
                ? ' AND class_session_id IS NULL'
                : ' AND class_session_id = ?';
            $bindings = array_merge(
                ['available', $expected + 1, (int) $slot->id,
                    (int) $slot->semester_id, (int) $slot->week_number, (int) $slot->day_of_week,
                    (int) $slot->venue_id, (string) $slot->start_time, (string) $slot->end_time,
                    (string) $slot->status, $expected],
                $slot->class_session_id === null ? [] : [(int) $slot->class_session_id],
            );

            $affected = DB::update(
                'UPDATE time_slots SET status = ?, class_session_id = NULL, version = ?'
                .' WHERE id = ? AND semester_id = ? AND week_number = ? AND day_of_week = ? AND venue_id = ?'
                .' AND start_time = ? AND end_time = ?'
                .' AND status = ? AND version = ?'.$ownerPredicate,
                $bindings,
            );

            if ($affected === 0) {
                throw new ReleaseDriftException($slot); // lost the version race — the caller rolls back
            }

            $freed[] = (int) $slot->id;

            if ($audit !== null) {
                $audit($slot);
            }
        }

        // delete ONLY the closed request's own links
        $this->deleteReceipt($request);

        return $freed;
    }

    /**
     * Whether ANOTHER active request also claims this cell — through a live
     * junction row or through the legacy anchor/span fallback. Active claims
     * are pending/approved; decided requests own nothing.
     */
    private function newerHolderExists(ReplacementRequest $request, TimeSlot $slot): bool
    {
        $other = DB::table('replacement_request_slots as l')
            ->join('replacement_requests as r', 'r.id', '=', 'l.replacement_request_id')
            ->where('l.time_slot_id', (int) $slot->id)
            ->where('r.id', '!=', (int) $request->id)
            ->whereIn('r.status', ['pending', 'approved'])
            ->exists();

        if ($other) {
            return true;
        }

        // the anchor/span fallback: other junction-LESS active claims anchored
        // in the same room/day/week whose mapped span could cover this cell
        $fallbacks = ReplacementRequest::query()
            ->where('id', '!=', (int) $request->id)
            ->where('semester_id', (int) $slot->semester_id)
            ->whereIn('status', ['pending', 'approved'])
            ->whereHas('replacementTimeSlot', fn ($q) => $q
                ->where('week_number', (int) $slot->week_number)
                ->where('day_of_week', (int) $slot->day_of_week)
                ->where('venue_id', (int) $slot->venue_id))
            ->whereDoesntHave('requestSlots')
            ->with('replacementTimeSlot')
            ->get();

        foreach ($fallbacks as $otherRequest) {
            $span = $this->mapper->spanFromAnchor($otherRequest->replacementTimeSlot, $otherRequest->replacement_span);

            if ($span === null) {
                continue;
            }

            $start = Carbon::parse((string) $span['start']);
            $end = Carbon::parse((string) $span['end']);
            $cellStart = Carbon::parse((string) $slot->start_time);

            if ($cellStart->greaterThanOrEqualTo($start) && $cellStart->lessThan($end)) {
                return true;
            }
        }

        return false;
    }

    private function deleteReceipt(ReplacementRequest $request): void
    {
        ReplacementRequestSlot::query()
            ->where('replacement_request_id', $request->id)
            ->delete();
    }
}

/**
 * A slot did not release in strict mode. The caller maps it to its own refusal
 * (RejectRequest turns it into the PL's "block runs into an existing booking"
 * message), so this class carries the failing slot and nothing else.
 */
final class ReleaseDriftException extends RuntimeException
{
    public function __construct(public readonly ?TimeSlot $slot = null)
    {
        parent::__construct('The reserved block is no longer fully pending.');
    }
}
