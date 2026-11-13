<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\ClassSession;
use App\Models\ReplacementRequest;
use App\Models\TimeSlot;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * THE original-occurrence ownership planner (SDD cancelled-slot-reuse, design
 * D3 — the ORIGINAL-RESERVATION rule).
 *
 * Given an occurrence `(semester_id, class_session_id, original_week)` and the
 * session's immutable recurring coordinates (its own day/venue/start/end), it
 * discovers every expected 30-minute cell, classifies each one, and reports
 * every row carrying the source id OUTSIDE the occurrence's coordinates —
 * never selecting by week or room alone, never guessing:
 *
 *   original      exactly one occupied row, source-owned coordinates, no live
 *                 claim and no successful slot-changing request audit
 *   available     every matching row already available (duplicate available
 *                 rows included, stale ids ignored) — no-op
 *   request_held  a pending/approved request owns the cell through a FULLY
 *                 coherent junction receipt or the documented junction-less
 *                 anchor/span fallback (via `ReplacementRequestMapper::spanFromAnchor()`)
 *                 — the live claim outranks any stale `class_session_id`
 *   missing       no expected row
 *   ambiguous     duplicate coordinates with a non-available row, unexplained
 *                 pending, mismatched occupied owner, inactive/orphan link,
 *                 a receipt that is not one contiguous same-room run opened
 *                 by the request's own anchor, a claim whose cell states
 *                 contradict the request (a PENDING claim on an OCCUPIED cell
 *                 is NOT owner proof), a malformed fallback span, conflicting
 *                 owners, a live claim on an available row, or a successful
 *                 request lifecycle contradicting the original provenance
 *
 * Provenance law (D3): provenance is read from SUCCESSFUL slot-changing
 * request audits (`old_status`/`new_status` both set AND different —
 * non-transition summaries and conflict attempts never qualify;
 * `class_cancelled`/maintenance receipts are outside the request family). A
 * stale matching class id never overrides that evidence.
 *
 * This service is SELECT-ONLY: it never locks and never writes. Runtime
 * callers plan provisionally, lock {@see OriginalOccurrencePlan::$candidateSlotIds}
 * and {@see OriginalOccurrencePlan::$claimingRequestIds} (plus their own
 * landing cells) inside their transaction, re-plan, and hand the fresh plan
 * to `App\Actions\ReleaseOriginalOccurrenceSlots`.
 */
final class OriginalOccurrenceReservation
{
    /** A replacement slot is a 30-minute cell (the DB CHECK enforces the row half). */
    private const CELL_MINUTES = 30;

    /**
     * THE strict default request-claim validation (SDD cancelled-slot-reuse) —
     * it and the occurrence planner's `claimsFor()` share ONE private
     * geometry/state core ({@see junctionRunVerdict()},
     * {@see fallbackRunVerdict()}, {@see claimStateMatches()}) so the rules
     * can never drift apart. This public proof runs the core with the STRICT
     * state policy; the planner's classification pass runs the SAME core with
     * the explicit `allowAvailable` tolerance so `classifyCell` can still
     * report `active_claim_on_available_row` — here an AVAILABLE receipt cell
     * is a refusal, never proof. SELECT-only: no locks, no writes.
     *
     * A claim only counts when it is ACTIVE (pending/approved — the opt-in is
     * fail closed, a decided request owns nothing) and its ENTIRE receipt run
     * is coherent: one semester/week/day/room, contiguous 30-minute cells
     * opened by the request's own anchor in the request's semester, every
     * member present, and every cell's status EQUAL to the claim state. The
     * release-consumed verdict is stricter than the occurrence planner's
     * duplicate tolerance on purpose: a receipt cell the claim no longer holds
     * (already freed) is a refusal, never proof.
     */
    public function validateRequestClaim(ReplacementRequest $request): RequestClaimEvidence
    {
        $status = (string) $request->status;

        if (! in_array($status, ['pending', 'approved'], true)) {
            return new RequestClaimEvidence('inactive_claim');
        }

        $expectedState = $status === 'pending' ? 'pending' : 'occupied';
        $junctionIds = $this->junctionSlotIds($request);

        if ($junctionIds !== []) {
            return $this->validateJunctionClaim($request, $junctionIds, $expectedState);
        }

        return $this->validateFallbackClaim($request, $expectedState);
    }

    /**
     * The junction receipt: one contiguous same-room run opened by the
     * request's own anchor, every member present, every cell in the claim
     * state. Landing = the junction ids exactly (the receipt owns its cells
     * by id — the seeded duplicate rows never enter the release set).
     *
     * @param  list<int>  $junctionIds
     */
    private function validateJunctionClaim(ReplacementRequest $request, array $junctionIds, string $expectedState): RequestClaimEvidence
    {
        $rows = [];

        foreach (TimeSlot::query()->whereIn('id', $junctionIds)->orderBy('start_time')->orderBy('id')->get() as $row) {
            $rows[(int) $row->id] = $row;
        }

        foreach ($junctionIds as $junctionId) {
            if (! isset($rows[$junctionId])) {
                // A receipt member whose slot row vanished — the run cannot be
                // read, so it proves nothing (fail closed).
                return new RequestClaimEvidence('orphan_link', [], $junctionIds, 'junction', $junctionId);
            }
        }

        $ordered = array_values($rows);
        $first = $ordered[0] ?? null;

        if ($first === null) {
            return new RequestClaimEvidence('orphan_link', [], $junctionIds, 'junction');
        }

        $verdict = $this->junctionRunVerdict(
            $ordered,
            (int) $request->replacement_time_slot_id,
            (int) $request->semester_id,
            $expectedState,
            false,
        );
        $reason = $verdict['reason'];
        $refusalSlotId = $verdict['refusalSlotId'];

        $cells = [];

        if ($reason === null) {
            foreach ($ordered as $row) {
                $cells[] = $this->cellShape($row);
            }

            usort($cells, fn (array $a, array $b): int => (int) $a['slot_id'] <=> (int) $b['slot_id']);
        }

        return new RequestClaimEvidence($reason, $cells, $junctionIds, 'junction', $reason === null ? null : $refusalSlotId);
    }

    /**
     * The junction-less anchor/span fallback: a real non-midnight-wrapping run
     * in the request's own semester. The proven cells are, per covered
     * coordinate, the row in the claim state — the seeded grid keeps duplicate
     * `available` rows on the same coordinates and they are never the claim.
     * A covered coordinate holding NO claim-state row is a stale receipt there
     * (D3 fail closed): refuse, never silently shrink the release set.
     */
    private function validateFallbackClaim(ReplacementRequest $request, string $expectedState): RequestClaimEvidence
    {
        // the FK anchor id is validated for real: a positive id is looked up,
        // anything else is simply not a readable anchor
        $anchorId = (int) $request->replacement_time_slot_id;
        $anchor = $anchorId > 0 ? TimeSlot::query()->find($anchorId) : null;

        $run = $this->fallbackRunVerdict($request, $anchor);
        $landing = $anchor !== null
            ? $this->sortIds(array_merge([$anchorId], $this->sameCoordinateSlotIds($anchor)))
            : [];

        if ($run['verdict'] !== 'placeable') {
            // A junction-less claim whose anchor cannot be read, whose span
            // cannot be mapped in the request's semester, or whose run wraps
            // past midnight proves nothing anywhere — refuse the release
            // rather than guess.
            return new RequestClaimEvidence('malformed_fallback', [], $landing, 'fallback',
                $anchor !== null ? $anchorId : null);
        }

        $span = $run['span'];

        $covered = TimeSlot::query()
            ->where('semester_id', (int) $request->semester_id)
            ->where('week_number', (int) $span['week'])
            ->where('day_of_week', (int) $span['day'])
            ->where('venue_id', (int) $span['venue_id'])
            ->where('start_time', '>=', (string) $span['start'])
            ->where('start_time', '<', (string) $span['end'])
            ->orderBy('id')
            ->get();

        $landing = $this->sortIds(array_merge([(int) $anchor->id], $this->slotIds($covered)));
        $coveredStarts = [];
        $provenStarts = [];
        $cells = [];
        $refusalSlotId = null;

        foreach ($covered as $row) {
            $coveredStarts[(string) $row->start_time] = true;

            if ($this->claimStateMatches((string) $row->status, $expectedState, false)) {
                $provenStarts[(string) $row->start_time] = true;
                $cells[] = $this->cellShape($row);
            } else {
                $refusalSlotId ??= (int) $row->id;
            }
        }

        if (count($provenStarts) !== count($coveredStarts)) {
            return new RequestClaimEvidence('claim_state_mismatch', [], $landing, 'fallback', $refusalSlotId);
        }

        usort($cells, fn (array $a, array $b): int => (int) $a['slot_id'] <=> (int) $b['slot_id']);

        return new RequestClaimEvidence(null, $cells, $landing, 'fallback');
    }

    /**
     * THE shared claim-state law (one truth, two explicit policies): a cell
     * speaks for a claim when its status equals the request's claim state
     * (pending → `pending`, approved → `occupied`). The occurrence planner's
     * classification pass passes `$allowAvailable = true` — an AVAILABLE row
     * stays quiet so `classifyCell` can still emit
     * `active_claim_on_available_row`; the strict public proof
     * (`validateRequestClaim()`) passes false — a freed cell is never proof.
     */
    private function claimStateMatches(string $rowStatus, string $expectedState, bool $allowAvailable): bool
    {
        return $rowStatus === $expectedState
            || ($allowAvailable && $rowStatus === 'available');
    }

    /**
     * THE shared junction-run verdict (ONE geometry/state law, two consumers
     * — the occurrence planner's `claimsFor()` and the strict public
     * `validateRequestClaim()` run this instead of two inline copies). The
     * rows are the run's members IN TIME ORDER, every member present (a
     * vanished member is the caller's own orphan verdict, not geometry).
     * Verdict:
     *
     *   reason — `contradictory_claim` (the anchor must OPEN its own run in
     *            the request's semester), `malformed_receipt` (half-cell
     *            width, a gap/overlap, or the run spread over more than one
     *            semester/week/day/room), `claim_state_mismatch` (a cell not
     *            in the claim state under the caller's policy) or null;
     *   refusalSlotId — the first cell that carried the verdict.
     *
     * @param  list<TimeSlot>  $orderedRows
     * @return array{reason: string|null, refusalSlotId: int|null}
     */
    private function junctionRunVerdict(array $orderedRows, int $anchorId, int $requestSemesterId, string $expectedState, bool $allowAvailable): array
    {
        $first = $orderedRows[0];
        $malformed = false;
        $contradictory = false;
        $stateMismatch = false;
        $refusalSlotId = null;
        $prevEnd = null;

        foreach ($orderedRows as $row) {
            $start = Carbon::parse((string) $row->start_time);
            $end = Carbon::parse((string) $row->end_time);

            if ((int) $start->diffInMinutes($end) !== self::CELL_MINUTES) {
                $malformed = true;
                $refusalSlotId ??= (int) $row->id;
            }

            if ($prevEnd !== null && ! $start->equalTo($prevEnd)) {
                $malformed = true; // a gap or an overlap breaks the run
                $refusalSlotId ??= (int) $row->id;
            }

            if (! $this->claimStateMatches((string) $row->status, $expectedState, $allowAvailable)) {
                // a stale receipt cell (freed already) or a claim under another
                // booking's state proves nothing — refuse, never steal
                $stateMismatch = true;
                $refusalSlotId ??= (int) $row->id;
            }

            if ((int) $row->semester_id !== (int) $first->semester_id
                || (int) $row->week_number !== (int) $first->week_number
                || (int) $row->day_of_week !== (int) $first->day_of_week
                || (int) $row->venue_id !== (int) $first->venue_id) {
                $malformed = true; // one run, one room/day/week — anything else is unreadable
                $refusalSlotId ??= (int) $row->id;
            }

            $prevEnd = $end;
        }

        if ((int) $first->id !== $anchorId
            || (int) $first->semester_id !== $requestSemesterId) {
            $contradictory = true; // the anchor must OPEN its own run in the request's semester
            $refusalSlotId ??= (int) $first->id;
        }

        return [
            'reason' => match (true) {
                $contradictory => 'contradictory_claim',
                $malformed => 'malformed_receipt',
                $stateMismatch => 'claim_state_mismatch',
                default => null,
            },
            'refusalSlotId' => $refusalSlotId,
        ];
    }

    /**
     * THE shared fallback-run geometry (ONE full-range law, two consumers —
     * `claimsFor()` and `validateRequestClaim()` no longer each carry their
     * own anchor/semester/span/wrap checks). The verdict of mapping the
     * junction-less claim's anchor + stored span:
     *
     *   placeable — the span maps to a real non-midnight-wrapping run; the
     *               span/start/end fields are set;
     *   unreadable — no readable anchor, no mappable span, or the anchor sits
     *               in another semester — the claim proves nothing anywhere;
     *   wrapping  — the mapped run wraps past midnight or reads backwards —
     *               D3: ambiguous wherever it appears.
     *
     * The junction run (when one exists) is authoritative and this mapping is
     * never consulted for it; here the stored span metadata is the only
     * geometry a legacy junction-less claim has.
     *
     * @return array{verdict: 'placeable'|'unreadable'|'wrapping', span: array{week: int, day: int, venue_id: int, start: string, end: string, count: int}|null, start: Carbon|null, end: Carbon|null}
     */
    private function fallbackRunVerdict(ReplacementRequest $request, ?TimeSlot $anchor): array
    {
        if ($anchor === null
            || (int) $anchor->semester_id !== (int) $request->semester_id) {
            return ['verdict' => 'unreadable', 'span' => null, 'start' => null, 'end' => null];
        }

        $span = $this->mapper->spanFromAnchor($anchor, $request->replacement_span);

        if ($span === null) {
            return ['verdict' => 'unreadable', 'span' => null, 'start' => null, 'end' => null];
        }

        $start = Carbon::parse((string) $span['start']);
        $end = Carbon::parse((string) $span['end']);

        if (! $end->greaterThan($start)) {
            // A run that wraps past midnight (or reads backwards) cannot be
            // placed on one day.
            return ['verdict' => 'wrapping', 'span' => $span, 'start' => $start, 'end' => $end];
        }

        return ['verdict' => 'placeable', 'span' => $span, 'start' => $start, 'end' => $end];
    }

    /**
     * The request's LIVE junction membership, ascending, unique — read fresh
     * from the DB, never from a possibly-stale eager load.
     *
     * @return list<int>
     */
    private function junctionSlotIds(ReplacementRequest $request): array
    {
        return $this->sortIds(array_values(DB::table('replacement_request_slots')
            ->where('replacement_request_id', (int) $request->id)
            ->pluck('time_slot_id')
            ->map(fn ($id): int => (int) $id)
            ->all()));
    }

    /**
     * @return list<int> the rows sitting on one TimeSlot's coordinates, any status
     */
    private function sameCoordinateSlotIds(?TimeSlot $row): array
    {
        if ($row === null) {
            return [];
        }

        return array_values(TimeSlot::query()
            ->where('semester_id', (int) $row->semester_id)
            ->where('week_number', (int) $row->week_number)
            ->where('day_of_week', (int) $row->day_of_week)
            ->where('venue_id', (int) $row->venue_id)
            ->where('start_time', (string) $row->start_time)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all());
    }

    /**
     * @return array{slot_id: int, semester_id: int, week_number: int, day_of_week: int, venue_id: int, start_time: string, end_time: string, status: string, version: int, class_session_id: int|null}
     */
    private function cellShape(TimeSlot $row): array
    {
        return [
            'slot_id' => (int) $row->id,
            'semester_id' => (int) $row->semester_id,
            'week_number' => (int) $row->week_number,
            'day_of_week' => (int) $row->day_of_week,
            'venue_id' => (int) $row->venue_id,
            'start_time' => (string) $row->start_time,
            'end_time' => (string) $row->end_time,
            'status' => (string) $row->status,
            'version' => (int) $row->version,
            'class_session_id' => $row->class_session_id !== null ? (int) $row->class_session_id : null,
        ];
    }

    /**
     * The request-audit actions that can move a slot's status. Only rows where
     * BOTH statuses are set AND differ count as evidence — failed
     * `occ_conflict` attempts, non-transition summaries and row-level
     * summaries carry equal/null statuses and never qualify.
     */
    private const LIFECYCLE_ACTIONS = ['submitted', 'approved', 'rejected', 'cancelled', 'completed'];

    public function __construct(private readonly ReplacementRequestMapper $mapper = new ReplacementRequestMapper) {}

    /**
     * The slot ids a runtime caller must lock before (re)planning: every row at
     * the occurrence's expected coordinates, every row carrying the source id
     * (out-of-range reporting), every member of any consulted receipt run, and
     * the anchor of every active request the anchor/span fallback consults (an
     * anchor may sit outside the session's own range and still cover its first
     * cells). Ascending, unique.
     *
     * @return list<int>
     */
    public function candidateSlotIds(ClassSession $session, int $week): array
    {
        $starts = $this->expectedStarts($session);

        if ($starts === null || $week < 1 || $week > 14) {
            return [];
        }

        $rows = $this->relevantRows($session, $week, $starts);
        $expected = $this->expectedRows($rows, $session, $week, $starts);

        return $this->sortIds(array_merge(
            $this->slotIds($rows),
            $this->activeReceiptSlotIds($expected),
            $this->fallbackAnchors($session, $week),
        ));
    }

    /**
     * Classify the occurrence. SELECT-only: call it provisionally before
     * locking and again inside the caller's transaction AFTER the rows and
     * claiming requests are locked — the second plan is the one a release may
     * consume.
     */
    public function plan(ClassSession $session, int $week): OriginalOccurrencePlan
    {
        $starts = $this->expectedStarts($session);

        if ($starts === null) {
            return $this->refusal($session, $week, 'malformed_session_range');
        }

        if ($week < 1 || $week > 14) {
            return $this->refusal($session, $week, 'invalid_week');
        }

        $sourceId = (int) $session->id;

        $rows = $this->relevantRows($session, $week, $starts);
        $expected = $this->expectedRows($rows, $session, $week, $starts);
        $outOfRange = $rows->reject(
            fn (TimeSlot $row): bool => $this->isAtCoordinates($row, $session, $week, $starts),
        )->values();

        $claims = $this->claimsFor($session, $week, $expected, $this->fallbackClaims($session, $week));
        $lifecycleSlotIds = $this->lifecycleSlotIds($session, $expected);

        $entries = [];

        foreach ($starts as $start) {
            $entries[] = $this->classifyCell(
                $start,
                $expected->filter(fn (TimeSlot $row): bool => (string) $row->start_time === $start)->sortBy('id')->values(),
                $sourceId,
                $claims,
                $lifecycleSlotIds,
            );
        }

        return new OriginalOccurrencePlan(
            $sourceId,
            (int) $session->semester_id,
            $week,
            (int) $session->day_of_week,
            (int) $session->venue_id,
            $entries,
            array_values($outOfRange->map(fn (TimeSlot $row): array => [
                'slot_id' => (int) $row->id,
                'semester_id' => (int) $row->semester_id,
                'week_number' => (int) $row->week_number,
                'day_of_week' => (int) $row->day_of_week,
                'venue_id' => (int) $row->venue_id,
                'start_time' => (string) $row->start_time,
                'end_time' => (string) $row->end_time,
                'status' => (string) $row->status,
                'version' => (int) $row->version,
            ])->all()),
            $this->sortIds(array_merge(
                $this->slotIds($rows),
                $claims['receiptSlotIds'],
                $this->fallbackAnchors($session, $week),
            )),
            $claims['requestIds'],
        );
    }

    /**
     * The occurrence's 30-minute cell starts ('H:i:s'), or null when the
     * recurring range is not a whole number of half-hour cells.
     *
     * @return list<string>|null
     */
    private function expectedStarts(ClassSession $session): ?array
    {
        $start = Carbon::parse((string) $session->start_time);
        $end = Carbon::parse((string) $session->end_time);

        if ($end->lessThanOrEqualTo($start)) {
            return null;
        }

        $minutes = (int) $start->diffInMinutes($end);

        if ($minutes <= 0 || $minutes % self::CELL_MINUTES !== 0 || ! $start->copy()->addMinutes($minutes)->equalTo($end)) {
            return null;
        }

        $starts = [];

        for ($offset = 0; $offset < $minutes; $offset += self::CELL_MINUTES) {
            $starts[] = $start->copy()->addMinutes($offset)->format('H:i:s');
        }

        return $starts;
    }

    /**
     * Every row the classification may read: the expected coordinates (any
     * owner — the room state decides, never the id alone) plus EVERY row
     * carrying the source id anywhere (out-of-range reporting). Ordered by id
     * so duplicate facts stay stable.
     *
     * @param  list<string>  $starts
     * @return Collection<int, TimeSlot>
     */
    private function relevantRows(ClassSession $session, int $week, array $starts): Collection
    {
        return TimeSlot::query()
            ->where(function ($query) use ($session, $week, $starts): void {
                $query->where('semester_id', (int) $session->semester_id)
                    ->where('week_number', $week)
                    ->where('day_of_week', (int) $session->day_of_week)
                    ->where('venue_id', (int) $session->venue_id)
                    ->whereIn('start_time', $starts);
            })
            ->orWhere('class_session_id', (int) $session->id)
            ->orderBy('id')
            ->get();
    }

    /**
     * The rows sitting exactly on the occurrence's coordinates.
     *
     * @param  Collection<int, TimeSlot>  $rows
     * @param  list<string>  $starts
     * @return Collection<int, TimeSlot>
     */
    private function expectedRows(Collection $rows, ClassSession $session, int $week, array $starts): Collection
    {
        return $rows->filter(
            fn (TimeSlot $row): bool => $this->isAtCoordinates($row, $session, $week, $starts),
        )->values();
    }

    /**
     * Whether the row sits on the occurrence's exact coordinates.
     *
     * @param  list<string>  $starts
     */
    private function isAtCoordinates(TimeSlot $row, ClassSession $session, int $week, array $starts): bool
    {
        return (int) $row->semester_id === (int) $session->semester_id
            && (int) $row->week_number === $week
            && (int) $row->day_of_week === (int) $session->day_of_week
            && (int) $row->venue_id === (int) $session->venue_id
            && in_array((string) $row->start_time, $starts, true);
    }

    /**
     * Active requests whose junction-less anchor/span claim could cover the
     * occurrence: pending/approved rows of the semester anchored in the
     * occurrence's week/day/room. The fallback only speaks for requests
     * WITHOUT junction rows — a live receipt owns its cells directly.
     *
     * @return Collection<int, ReplacementRequest>
     */
    private function fallbackClaims(ClassSession $session, int $week): Collection
    {
        return ReplacementRequest::query()
            ->where('semester_id', (int) $session->semester_id)
            ->whereIn('status', ['pending', 'approved'])
            ->whereHas('replacementTimeSlot', function ($query) use ($session, $week): void {
                $query->where('semester_id', (int) $session->semester_id)
                    ->where('week_number', $week)
                    ->where('day_of_week', (int) $session->day_of_week)
                    ->where('venue_id', (int) $session->venue_id);
            })
            ->with('replacementTimeSlot')
            ->get();
    }

    /**
     * The fallback candidates' anchors — the caller must be able to lock the
     * anchor of every claim the classification may consult.
     *
     * @return list<int>
     */
    private function fallbackAnchors(ClassSession $session, int $week): array
    {
        return array_values($this->fallbackClaims($session, $week)
            ->pluck('replacement_time_slot_id')
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all());
    }

    /**
     * Every slot id of the receipt runs the claims consult: junction rows
     * touching the expected coordinates plus the FULL run of each such active
     * request (a receipt member outside the occurrence still decides whether
     * the run is coherent, so it must be locked too).
     *
     * @param  Collection<int, TimeSlot>  $expected
     * @return list<int>
     */
    private function activeReceiptSlotIds(Collection $expected): array
    {
        $slotIds = $this->slotIds($expected);

        if ($slotIds === []) {
            return [];
        }

        $activeIds = DB::table('replacement_request_slots as l')
            ->join('replacement_requests as r', 'r.id', '=', 'l.replacement_request_id')
            ->whereIn('l.time_slot_id', $slotIds)
            ->whereIn('r.status', ['pending', 'approved'])
            ->pluck('l.replacement_request_id')
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values();

        if ($activeIds->isEmpty()) {
            return [];
        }

        return array_values(DB::table('replacement_request_slots')
            ->whereIn('replacement_request_id', $activeIds->all())
            ->pluck('time_slot_id')
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all());
    }

    /**
     * Resolve every live ownership claim on the expected rows.
     *
     * A claim only counts when its ENTIRE receipt run is coherent: one
     * semester/week/day/room, contiguous 30-minute cells opened by the
     * request's own anchor, and every cell's status matching the request
     * state (pending → pending, approved → occupied). Anything else — an
     * off-room/off-day/non-contiguous run, a contradictory anchor, an
     * orphan/inactive link, a pending claim on an occupied cell, a malformed
     * fallback span — is ambiguous evidence, never a proven owner (D3, fail
     * closed). Claims anchored in another room/week are unrelated and say
     * nothing about this occurrence.
     *
     * The run/state verdicts come from the SAME private core
     * `validateRequestClaim()` uses ({@see junctionRunVerdict()},
     * {@see fallbackRunVerdict()}, {@see claimStateMatches()}) — here with
     * the planner's explicit AVAILABLE tolerance: an available receipt cell
     * stays quiet so `classifyCell` can emit `active_claim_on_available_row`,
     * while the strict public proof refuses the same cell (the state policy
     * is the only difference, and it is deliberate).
     *
     * @param  Collection<int, TimeSlot>  $expected
     * @param  Collection<int, ReplacementRequest>  $fallbacks
     * @return array{bySlot: array<int, list<int>>, issues: array<int, string>, requestIds: list<int>, receiptSlotIds: list<int>}
     */
    private function claimsFor(ClassSession $session, int $week, Collection $expected, Collection $fallbacks): array
    {
        $slotIds = $this->slotIds($expected);
        $bySlot = [];
        $issues = [];
        $requestIds = [];
        $receiptSlotIds = [];

        if ($slotIds === []) {
            return ['bySlot' => [], 'issues' => [], 'requestIds' => [], 'receiptSlotIds' => []];
        }

        // The LIVE receipts — one link per slot (the UNIQUE time_slot_id law).
        $links = DB::table('replacement_request_slots as l')
            ->leftJoin('replacement_requests as r', 'r.id', '=', 'l.replacement_request_id')
            ->whereIn('l.time_slot_id', $slotIds)
            ->get([
                'l.time_slot_id',
                'r.id as request_id',
                'r.status as request_status',
                'r.semester_id as request_semester_id',
                'r.replacement_time_slot_id as anchor_id',
            ]);

        $activeInfo = [];

        foreach ($links as $link) {
            if ($link->request_id === null) {
                // A junction row whose request no longer exists — fail closed.
                $issues[(int) $link->time_slot_id] ??= 'orphan_link';

                continue;
            }

            if (! in_array((string) $link->request_status, ['pending', 'approved'], true)) {
                // A junction the request family no longer owns — drift, not evidence.
                $issues[(int) $link->time_slot_id] ??= 'inactive_link';

                continue;
            }

            $requestId = (int) $link->request_id;
            $activeInfo[$requestId] ??= [
                'status' => (string) $link->request_status,
                'semester_id' => (int) $link->request_semester_id,
                'anchor_id' => (int) $link->anchor_id,
            ];
            $requestIds[] = $requestId;
        }

        $activeIds = array_keys($activeInfo);

        /** @var array<int, list<int>> $junctionSlots */
        $junctionSlots = $activeIds === []
            ? []
            : DB::table('replacement_request_slots')
                ->whereIn('replacement_request_id', $activeIds)
                ->get(['replacement_request_id', 'time_slot_id'])
                ->groupBy('replacement_request_id')
                ->map(fn ($group): array => $group->pluck('time_slot_id')
                    ->map(fn ($id): int => (int) $id)
                    ->values()
                    ->all())
                ->all();

        $allReceiptIds = array_values(array_unique(array_merge(...array_values($junctionSlots))));

        $receiptRows = [];

        foreach (TimeSlot::query()->whereIn('id', $allReceiptIds)->get() as $row) {
            $receiptRows[(int) $row->id] = $row;
        }

        foreach ($activeIds as $requestId) {
            $receiptIds = $junctionSlots[$requestId];
            $receiptSlotIds = array_merge($receiptSlotIds, $receiptIds);
            $intersecting = array_values(array_intersect($receiptIds, $slotIds));
            $info = $activeInfo[$requestId];

            $rows = [];

            foreach ($receiptIds as $receiptId) {
                $row = $receiptRows[$receiptId] ?? null;

                if ($row === null) {
                    // A receipt member whose slot row vanished — the run
                    // cannot be read, so it proves nothing (fail closed).
                    foreach ($intersecting as $slotId) {
                        $issues[$slotId] ??= 'orphan_link';
                    }

                    continue 2;
                }

                $rows[] = $row;
            }

            if ($rows === []) {
                continue; // nothing left of the receipt to speak for these cells
            }

            usort($rows, fn (TimeSlot $a, TimeSlot $b): int => strcmp((string) $a->start_time, (string) $b->start_time));

            $expectedState = $info['status'] === 'pending' ? 'pending' : 'occupied';
            $reason = $this->junctionRunVerdict(
                $rows,
                $info['anchor_id'],
                $info['semester_id'],
                $expectedState,
                true,
            )['reason'];

            if ($reason !== null) {
                foreach ($intersecting as $slotId) {
                    $issues[$slotId] ??= $reason;
                }

                continue;
            }

            foreach ($intersecting as $slotId) {
                $bySlot[$slotId][] = $requestId;
            }
        }

        // The anchor/span fallback — junction-less active claims only (D3).
        if ($fallbacks->isNotEmpty()) {
            $fallbackIds = $fallbacks->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->values()
                ->all();

            $withReceipts = DB::table('replacement_request_slots')
                ->whereIn('replacement_request_id', $fallbackIds)
                ->pluck('replacement_request_id')
                ->map(fn ($id): int => (int) $id)
                ->unique()
                ->values()
                ->all();

            foreach ($fallbacks as $request) {
                $requestId = (int) $request->id;
                $requestIds[] = $requestId;

                if (in_array($requestId, $withReceipts, true)) {
                    continue; // it owns cells through junctions; the fallback is not its claim
                }

                $anchor = $request->replacementTimeSlot;

                if ($anchor === null) {
                    // A junction-less claim without a readable anchor cannot
                    // be checked — refuse the occurrence rather than guess.
                    foreach ($slotIds as $slotId) {
                        $issues[$slotId] ??= 'malformed_fallback';
                    }

                    continue;
                }

                $run = $this->fallbackRunVerdict($request, $anchor);

                if ($run['verdict'] === 'unreadable') {
                    continue; // a claim that cannot be placed anywhere covers nothing here
                }

                $span = $run['span'];

                if ((int) $span['week'] !== $week
                    || (int) $span['day'] !== (int) $session->day_of_week
                    || (int) $span['venue_id'] !== (int) $session->venue_id) {
                    continue; // a claim that does not agree with this occurrence covers nothing here
                }

                if ($run['verdict'] === 'wrapping') {
                    // A run that wraps past midnight (or reads backwards)
                    // cannot be placed on one day — refuse the occurrence
                    // rather than protect arbitrary wraparound cells.
                    foreach ($slotIds as $slotId) {
                        $issues[$slotId] ??= 'malformed_fallback';
                    }

                    continue;
                }

                $runStart = $run['start'];
                $runEnd = $run['end'];

                $expectedState = (string) $request->status === 'pending' ? 'pending' : 'occupied';

                foreach ($expected as $row) {
                    if (! $this->covers(Carbon::parse((string) $row->start_time), $runStart, $runEnd)) {
                        continue;
                    }

                    if (! $this->claimStateMatches((string) $row->status, $expectedState, true)) {
                        // A pending claim on an occupied cell (or an approved
                        // claim on a pending one) is contradictory status,
                        // not valid owner proof.
                        $issues[(int) $row->id] ??= 'claim_state_mismatch';

                        continue;
                    }

                    $bySlot[(int) $row->id][] = $requestId;
                }
            }
        }

        // One cell, one live owner: two distinct claims contradict each other.
        foreach ($bySlot as $slotId => $owners) {
            $distinct = array_values(array_unique($owners));

            if (count($distinct) > 1) {
                unset($bySlot[$slotId]);
                $issues[$slotId] ??= 'conflicting_claims';
            } else {
                $bySlot[$slotId] = $distinct;
            }
        }

        return [
            'bySlot' => $bySlot,
            'issues' => $issues,
            'requestIds' => array_values(array_unique($requestIds)),
            'receiptSlotIds' => $this->sortIds($receiptSlotIds),
        ];
    }

    /**
     * The successful slot-changing request lifecycle evidence on the rows that
     * would otherwise classify as the pristine recurring reservation. Conflict
     * attempts carry null statuses and never qualify; non-transition summaries
     * (old == new) are not slot changes; `class_cancelled`
     * (cancellation/maintenance) receipts are outside the request family.
     *
     * @param  Collection<int, TimeSlot>  $expected
     * @return list<int>
     */
    private function lifecycleSlotIds(ClassSession $session, Collection $expected): array
    {
        $candidates = $expected
            ->filter(fn (TimeSlot $row): bool => (string) $row->status === 'occupied'
                && $row->class_session_id !== null
                && (int) $row->class_session_id === (int) $session->id)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values();

        if ($candidates->isEmpty()) {
            return [];
        }

        return array_values(AuditLog::query()
            ->whereIn('time_slot_id', $candidates->all())
            ->whereIn('action', self::LIFECYCLE_ACTIONS)
            ->whereColumn('old_status', '!=', 'new_status')
            ->whereNotNull('old_status')
            ->whereNotNull('new_status')
            ->pluck('time_slot_id')
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all());
    }

    /**
     * Classify ONE coordinate from its rows, the resolved claims and the
     * lifecycle evidence. Order matters: duplicate ambiguity, then claim
     * issues, then live ownership, then the row's own state.
     *
     * @param  Collection<int, TimeSlot>  $rows
     * @param  array{bySlot: array<int, list<int>>, issues: array<int, string>, requestIds: array<int, int>, receiptSlotIds: list<int>}  $claims
     * @param  list<int>  $lifecycleSlotIds
     */
    private function classifyCell(string $start, Collection $rows, int $sourceId, array $claims, array $lifecycleSlotIds): OriginalOccurrenceEntry
    {
        $end = Carbon::parse($start)->addMinutes(self::CELL_MINUTES)->format('H:i:s');

        if ($rows->isEmpty()) {
            return new OriginalOccurrenceEntry($start, $end, OriginalOccurrencePlan::MISSING, reason: 'missing_row');
        }

        $duplicates = $this->slotIds($rows);
        $primary = $rows->first();
        $anyNonAvailable = $rows->contains(fn (TimeSlot $row): bool => (string) $row->status !== 'available');

        // Duplicate coordinates with any non-available row: the initial
        // reservation cannot be told apart from the copies — refuse.
        if ($anyNonAvailable && count($duplicates) > 1) {
            return $this->entry($start, $end, $primary, $duplicates, OriginalOccurrencePlan::AMBIGUOUS, 'duplicate_coordinates');
        }

        foreach ($duplicates as $slotId) {
            if (isset($claims['issues'][$slotId])) {
                return $this->entry($start, $end, $primary, $duplicates, OriginalOccurrencePlan::AMBIGUOUS, $claims['issues'][$slotId]);
            }
        }

        $owners = [];

        foreach ($duplicates as $slotId) {
            foreach ($claims['bySlot'][$slotId] ?? [] as $owner) {
                $owners[] = $owner;
            }
        }

        $owners = array_values(array_unique($owners));

        // Request ownership outranks every legacy id — including on a row that
        // still carries the source session's own id (D3/S03).
        if ($owners !== []) {
            if ($anyNonAvailable) {
                return new OriginalOccurrenceEntry(
                    $start,
                    $end,
                    OriginalOccurrencePlan::REQUEST_HELD,
                    (int) $primary->id,
                    (string) $primary->status,
                    $primary->class_session_id !== null ? (int) $primary->class_session_id : null,
                    (int) $primary->version,
                    $duplicates,
                    $owners[0],
                );
            }

            // An available row under a live claim is contradictory data.
            return $this->entry($start, $end, $primary, $duplicates, OriginalOccurrencePlan::AMBIGUOUS, 'active_claim_on_available_row', $owners[0]);
        }

        if ($anyNonAvailable) {
            $status = (string) $primary->status;
            $ownerId = $primary->class_session_id !== null ? (int) $primary->class_session_id : null;

            if ($status === 'pending') {
                return $this->entry($start, $end, $primary, $duplicates, OriginalOccurrencePlan::AMBIGUOUS, 'unexplained_pending');
            }

            if ($status === 'occupied') {
                if ($ownerId !== $sourceId) {
                    return $this->entry($start, $end, $primary, $duplicates, OriginalOccurrencePlan::AMBIGUOUS, 'mismatched_occupied_owner');
                }

                if (in_array((int) $primary->id, $lifecycleSlotIds, true)) {
                    return $this->entry($start, $end, $primary, $duplicates, OriginalOccurrencePlan::AMBIGUOUS, 'provenance_contradicted');
                }

                // The pristine recurring reservation: matching coordinates,
                // matching owner, no claim, no lifecycle evidence.
                return $this->entry($start, $end, $primary, $duplicates, OriginalOccurrencePlan::ORIGINAL);
            }

            // The status CHECK makes this unreachable; refuse rather than guess.
            return $this->entry($start, $end, $primary, $duplicates, OriginalOccurrencePlan::AMBIGUOUS, 'unknown_status');
        }

        // Every matching row already available — stale ids and duplicates included.
        return $this->entry($start, $end, $primary, $duplicates, OriginalOccurrencePlan::AVAILABLE);
    }

    /**
     * Whether the cell start falls inside the claim's half-open run
     * `[start, end)`. Malformed (midnight-wrapping) runs are refused by the
     * caller before this runs, so a plain interval read suffices.
     */
    private function covers(Carbon $cellStart, Carbon $runStart, Carbon $runEnd): bool
    {
        return $cellStart->greaterThanOrEqualTo($runStart) && $cellStart->lessThan($runEnd);
    }

    /**
     * @param  list<int>  $duplicates
     */
    private function entry(string $start, string $end, TimeSlot $row, array $duplicates, string $classification, ?string $reason = null, ?int $heldByRequestId = null): OriginalOccurrenceEntry
    {
        return new OriginalOccurrenceEntry(
            $start,
            $end,
            $classification,
            (int) $row->id,
            (string) $row->status,
            $row->class_session_id !== null ? (int) $row->class_session_id : null,
            (int) $row->version,
            $duplicates,
            $heldByRequestId,
            $reason,
        );
    }

    /**
     * @param  Collection<int, TimeSlot>  $rows
     * @return list<int>
     */
    private function slotIds(Collection $rows): array
    {
        return array_values($rows->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->all());
    }

    /**
     * @param  list<int>  $ids
     * @return list<int>
     */
    private function sortIds(array $ids): array
    {
        $unique = array_values(array_unique(array_map('intval', $ids)));
        sort($unique);

        return $unique;
    }

    private function refusal(ClassSession $session, int $week, string $reason): OriginalOccurrencePlan
    {
        return new OriginalOccurrencePlan(
            (int) $session->id,
            (int) $session->semester_id,
            $week,
            (int) $session->day_of_week,
            (int) $session->venue_id,
            refusalReason: $reason,
        );
    }
}
