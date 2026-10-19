<?php

namespace App\Services;

use App\Models\ReplacementRequest;
use App\Models\TimeSlot;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * THE shared request-row mapper (SDD span-aware-approval-flow — design D5 /
 * spec display-reads R3; the §10.0.6 promote-on-3rd-duplicate extraction).
 *
 * Owns the computations that used to live twice (ApiReadController::myRequests
 * + MyRequestHistory::requestRow) and once more is needed by the PL queue:
 *
 *  - THE DATA-DRIVEN span read (junction min/max) — for ANY request status
 *    (approved reads the junction too, Q3=A); whenever the junction yields NO
 *    range (legacy pending anchor-only rows, rejected/cancelled after their
 *    release deletes the receipt), it falls back to the LEGACY anchor slot —
 *    one rule, never bucket-keyed (spec RR1 🔴).
 *  - the class-identity row (dates/days/times/venue/course keys).
 *  - slotValidity/conflictReason semantics (pending rows: the ApprovalValidity
 *    2-case map; decided rows: 'valid' + null — past contention, RR2 💡).
 *
 * Per-surface BYTE rules stay local to each consumer (their pinned output
 * shapes): the API keeps its STUDENT_COUNTS-based cohort map + float duration;
 * the history page keeps its spaced labels + int duration; the queue takes
 * the canonical toRow() verbatim. The consumers UNSET the two badge keys their
 * historical payload doesn't carry — the computation is shared, the shapes
 * stay byte-identical (their pinned tests prove it).
 */
final class ReplacementRequestMapper
{
    /**
     * The DISPLAY timezone (user 2026-10-19): the app/db clock is UTC while
     * the USER's clock is Asia/Kuala_Lumpur (+08) — Requested At renders the
     * MYT form. Apply ONLY on the Requested At path (scoped per the user's
     * go-ahead; the other timestamps stay reported-only).
     */
    public const DISPLAY_TZ = 'Asia/Kuala_Lumpur';

    /** The MYT-form stamp — byte-shape identical with the previous UTC form. */
    public function stampDisplay(?CarbonInterface $stamp, string $format): ?string
    {
        return $stamp === null
            ? null
            : $stamp->copy()->setTimezone(self::DISPLAY_TZ)->format($format);
    }

    /** The MYT-form stamp — byte-shape identical with the previous UTC form. */
    public function requestedAtDisplay(CarbonInterface $stamp): string
    {
        return $stamp->copy()->setTimezone(self::DISPLAY_TZ)->format('Y-m-d\TH:i:s');
    }

    private const DAY_NAMES = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

    /**
     * The canonical PL-queue row (frozen template's `requests` §2.13 shape).
     *
     * @return array<string, mixed>
     */
    public function toRow(ReplacementRequest $r, Carbon $semesterStart, string $requestedBucket, ?ValidityResult $validity = null): array
    {
        $session = $r->classSession;
        $identity = $this->classRow($r, $semesterStart);
        $span = $this->replacementStrings($r, $semesterStart);

        // Pending rows ride the shared predicate; decided rows are past
        // contention ('valid' + no conflict text — RR2 💡).
        if ($validity === null) {
            $validity = (new ApprovalValidity)->evaluate($r);
        }
        if ((string) $r->status === 'pending') {
            $slotValidity = $validity->valid ? 'valid' : 'conflict';
            $conflictReason = $validity->reason;
        } else {
            $slotValidity = 'valid';
            $conflictReason = null;
        }

        $prettyStatus = ucfirst((string) $r->status);

        // The cohort metadata (the queue bucket's byte shape: SPACED labels +
        // a values-array of counts; api/history adapters pin their own forms).
        $cohorts = $r->classSession->cohorts
            ->map(fn ($c): array => [
                'label' => ($c->programme !== null ? (string) $c->programme->programme_code : '—')
                    .$c->current_year.' (S'.$c->semester.') G'.$c->tutorial_group,
                'count' => (int) $c->student_count,
            ]);
        $totalStudents = (int) $cohorts->sum('count');

        return $identity + [
            'id' => (int) $r->id,
            // §2.13's queued row names the PROPOSER `lecturer`:
            'lecturer' => $r->proposer !== null ? (string) $r->proposer->name : '',
            'requestedAt' => $this->requestedAtDisplay($r->submitted_at),
            'reviewedAt' => $this->stampDisplay($r->decided_at, 'Y-m-d\TH:i:s'),
            'reviewedBy' => $r->approver !== null ? (string) $r->approver->name : null,
            'status' => $prettyStatus,
            'rejectionReason' => $r->rejection_reason,
            'replacementDate' => $span['date'],
            'replacementTime' => $span['time'],
            'replacementVenue' => $span['venue'],
            'cohorts' => $cohorts->pluck('label')->values()->all(),
            'cohortCounts' => $cohorts->pluck('count')->values()->all(),
            'totalStudents' => $totalStudents,
            'remarks' => $r->remarks,
            'slotValidity' => $slotValidity,
            'conflictReason' => $conflictReason,
            // `requestedBucket` is informational only — the fallback path is
            // DATA-DRIVEN and never branches on it (spec RR1 🔴).
            'bucket' => $requestedBucket,
        ];
    }

    /**
     * The class-identity row keys (shared by every consumer).
     *
     * @return array<string, mixed>
     */
    public function classRow(ReplacementRequest $r, Carbon $semesterStart): array
    {
        $session = $r->classSession;
        $start = Carbon::parse((string) $session->start_time);
        $end = Carbon::parse((string) $session->end_time);
        // int-hours duration (the history page's byte rule; the API adapter
        // recomputes its own float form per its pinned shape).
        $duration = (int) ($start->diffInMinutes($end) / 60);

        return [
            'courseCode' => (string) $session->module->module_code,
            'courseName' => (string) $session->module->module_name,
            'classType' => (string) $session->session_type,
            'classDate' => $semesterStart->copy()
                ->addWeeks((int) $r->week_number - 1)
                ->addDays((int) $session->day_of_week)
                ->toDateString(),
            'classDay' => self::DAY_NAMES[(int) $session->day_of_week] ?? '',
            'timeStart' => $start->format('H:i'),
            'timeEnd' => $end->format('H:i'),
            'duration' => $duration,
            'venue' => (string) $session->venue->room_code,
        ];
    }

    /**
     * THE span read (data-driven; Q3=A switch).
     *
     * 1. junction min/max (ANY status — approved included);
     * 2. else the LEGACY anchor slot (`replacement_time_slot_id`).
     * 3. null when neither exists (a request row whose anchor was removed).
     *
     * @return array{week: int, day: int, venue_id: int, start: string, end: string, count: int}|null
     */
    public function span(ReplacementRequest $r): ?array
    {
        $span = DB::table('replacement_request_slots')
            ->join('time_slots', 'replacement_request_slots.time_slot_id', '=', 'time_slots.id')
            ->where('replacement_request_slots.replacement_request_id', (int) $r->id)
            ->orderBy('time_slots.start_time')
            ->get(['time_slots.week_number', 'time_slots.day_of_week', 'time_slots.venue_id', 'time_slots.start_time', 'time_slots.end_time']);

        if ($span->isNotEmpty()) {
            return [
                'week' => (int) $span->first()->week_number,
                'day' => (int) $span->first()->day_of_week,
                'venue_id' => (int) $span->first()->venue_id,
                'start' => (string) $span->first()->start_time,
                'end' => (string) $span->last()->end_time,
                'count' => $span->count(),
            ];
        }

        $anchor = $r->replacementTimeSlot;
        if ($anchor === null) {
            return null;
        }

        return [
            'week' => (int) $anchor->week_number,
            'day' => (int) $anchor->day_of_week,
            'venue_id' => (int) $anchor->venue_id,
            'start' => (string) $anchor->start_time,
            'end' => (string) $anchor->end_time,
            'count' => 1,
        ];
    }

    /** A DISPLAY slot modelled on the span read (venue relation resolvable: the 2026-10-17 line-132 fix). */
    public function displaySlot(ReplacementRequest $r): ?TimeSlot
    {
        $span = $this->span($r);
        if ($span === null) {
            return null;
        }

        $session = $r->classSession;

        return new TimeSlot([
            'semester_id' => (int) $session->semester_id,
            'week_number' => $span['week'],
            'day_of_week' => $span['day'],
            'venue_id' => $span['venue_id'],
            'start_time' => $span['start'],
            'end_time' => $span['end'],
        ]);
    }

    /**
     * The replacement date/time/venue strings ('14:00 – 16:00' format).
     *
     * @return array{date: ?string, time: ?string, venue: ?string}
     */
    public function replacementStrings(ReplacementRequest $r, Carbon $semesterStart): array
    {
        $slot = $this->displaySlot($r);
        if ($slot === null || $slot->venue === null) {
            return ['date' => null, 'time' => null, 'venue' => null];
        }

        return [
            'date' => $semesterStart->copy()
                ->addWeeks((int) $slot->week_number - 1)
                ->addDays((int) $slot->day_of_week)
                ->toDateString(),
            'time' => Carbon::parse((string) $slot->start_time)->format('H:i').' – '.Carbon::parse((string) $slot->end_time)->format('H:i'),
            'venue' => (string) $slot->venue->room_code,
        ];
    }
}
