<?php

namespace App\Services;

use App\Models\ReplacementRequest;
use App\Models\TimeSlot;
use App\Models\Venue;
use App\Support\LocalTime;
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
     * The DISPLAY timezone (user 2026-10-19). The TRUTH now lives in
     * `App\Support\LocalTime` (2026-11-04); this constant is kept as the
     * compatibility mirror so existing readers keep working.
     */
    public const DISPLAY_TZ = LocalTime::TZ;

    /** The MYT-form stamp — byte-shape identical with the previous UTC form. */
    public function stampDisplay(?CarbonInterface $stamp, string $format): ?string
    {
        return LocalTime::stamp($stamp, $format);
    }

    /** The MYT-form stamp — byte-shape identical with the previous UTC form. */
    public function requestedAtDisplay(CarbonInterface $stamp): string
    {
        return (string) LocalTime::stamp($stamp, 'Y-m-d\TH:i:s');
    }

    private const DAY_NAMES = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

    /** SDD notif-new-request-ux (the composer's desc/tip fact lines): the public
     *  read of the private DAY_NAMES — the promote-on-3rd law (the day-name map
     *  is now the mapper's; no consumer hardcodes its own). */
    public static function dayName(int|string $day): string
    {
        return self::DAY_NAMES[(int) $day] ?? '';
    }

    /**
     * request-completed-status (2026-11-03) — C1/C2: the EFFECTIVE status.
     *
     * Derived, never stored: an `approved` request whose replacement class
     * DATE is strictly before today reads `completed`. The date is the SAME one
     * `replacementStrings()` gives the row to print, so the badge and the
     * Replacement Date column can never contradict each other. Comparison is
     * by DATE (`startOfDay`), never date-and-time: a class at 09:00 today is
     * not "past" at 10:00 today.
     *
     * Falls back to the stored status when the replacement date cannot be
     * resolved (no slot / no venue) — the app never GUESSES Completed.
     *
     * `$replacementDate` (replacement-merge-one-table T2, 2026-11-04) lets a
     * caller that ALREADY resolved the date pass it in, so the derived status
     * costs no second span read. Every existing caller still passes two
     * arguments and behaves byte-identically.
     *
     * Lowercase in, lowercase out: every consumer applies `ucfirst()` where it
     * needs the pretty form (the home payload wants the raw value).
     */
    public function effectiveStatus(ReplacementRequest $r, Carbon $semesterStart, ?string $replacementDate = null): string
    {
        $stored = (string) $r->status;

        if ($stored !== 'approved') {
            return $stored;
        }

        $date = $replacementDate ?? $this->replacementStrings($r, $semesterStart)['date'];

        if ($date === null) {
            return $stored;             // unreadable date — never guess Completed
        }

        return Carbon::parse($date)->startOfDay()->lt(today()->startOfDay())
            ? 'completed'
            : 'approved';
    }

    /**
     * replacement-merge-one-table (M5/T3, 2026-11-04) — THE six-state vocabulary.
     * Pure, no queries: the page never decides a status, a label or a suffix, so
     * the badge, the card, the tab title and the sentence cannot disagree.
     *
     * `<n>` uses the SERVER's today() — the same clock `effectiveStatus()`
     * compares against — so "Completed — 1 day ago" can never contradict the
     * status that produced it (a browser in another timezone would).
     *
     * @return array{label: string, short: string, sentence: string, tip: string}
     */
    public function statusVocabulary(?string $status, ?string $date): array
    {
        return [
            'label' => $this->statusLabel($status, $date),
            'short' => $this->statusShort($status),
            'sentence' => $this->statusSentence($status, $date),
            'tip' => $this->statusTip($status),
        ];
    }

    /** The badge text (M5). A `completed` row carries how long ago, when known. */
    public function statusLabel(?string $status, ?string $date): string
    {
        if ($status === null) {
            return 'Need to Arrange';
        }

        if ($status === 'completed') {
            $ago = $this->sinceLabel($date);

            return $ago === null ? 'Completed' : 'Completed — '.$ago;
        }

        return match ($status) {
            'pending' => 'Pending — Waiting for Approval',
            'approved' => 'Approved',
            'rejected' => 'Rejected',
            'cancelled' => 'Cancelled by YOU',
            default => ucfirst($status),
        };
    }

    /** The short form — the pop-up's tab title and the badge's CSS key source. */
    public function statusShort(?string $status): string
    {
        return match ($status) {
            null => 'Need to Arrange',
            'pending' => 'Pending',
            'approved' => 'Approved',
            'rejected' => 'Rejected',
            'cancelled' => 'Cancelled',
            'completed' => 'Completed',
            default => ucfirst($status),
        };
    }

    /** The one plain sentence under the status tab — keyed by the STATUS, never by the label. */
    public function statusSentence(?string $status, ?string $date): string
    {
        return match ($status) {
            null => 'No replacement has been proposed for this class yet.',
            'pending' => 'The proposed replacement is waiting for the PL to approve or reject it.',
            'approved' => 'The PL approved the proposed replacement, so this class will run at the new time.',
            'rejected' => 'The PL declined the proposed replacement, so a different slot has to be arranged.',
            'cancelled' => 'You cancelled this request, so the class is free to be arranged again.',
            'completed' => $this->sinceLabel($date) === null || $this->sinceLabel($date) === 'today'
                ? 'The replacement took place today.'
                : 'The replacement took place '.$this->sinceLabel($date).'.',
            default => '',
        };
    }

    /**
     * The badge's `data-tip`. The five decided states keep the shipped copy
     * (the My Requests tooltips, unchanged by M5 — only the LABELS change); the
     * no-request state gets the outlined-pill meaning. An unknown state returns
     * '' so the badge simply carries no tip rather than an invented one.
     */
    public function statusTip(?string $status): string
    {
        return match ($status) {
            null => 'No request has been made for this class yet — it still needs arranging.',
            'pending' => "Your request has been Submitted and is AWAITING the Programme Leader's decision (Approve or Reject).",
            'approved' => 'The Programme Leader has APPROVED your request.',
            'rejected' => 'The Programme Leader has REJECTED your request.',
            'cancelled' => 'You MANUALLY CANCELLED this request.',
            'completed' => 'The replacement class has been CONDUCTED — this request is complete.',
            default => '',
        };
    }

    /**
     * "today" / "1 day ago" / "n days ago" from a `Y-m-d` date, or null when the
     * date cannot be read (the caller then omits the suffix rather than inventing one).
     */
    private function sinceLabel(?string $date): ?string
    {
        if ($date === null || $date === '') {
            return null;
        }

        $days = (int) abs(Carbon::parse($date)->startOfDay()->diffInDays(today()->startOfDay()));

        return $days <= 0 ? 'today' : ($days === 1 ? '1 day ago' : $days.' days ago');
    }

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

        // request-completed-status R2 (C3): the PL queue AND the JSON API read
        // the one derived status — an approved request whose replacement class
        // has already happened reads 'Completed' (C1/C2, nothing stored).
        $prettyStatus = ucfirst($this->effectiveStatus($r, $semesterStart));

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

        return $this->spanFromAnchor($r->replacementTimeSlot);
    }

    /**
     * span()'s step 2, extracted (replacement-merge-one-table T1, 2026-11-04) so
     * the batch caller reuses the body instead of copying it: the legacy anchor
     * slot (`replacement_time_slot_id`) is the ONLY surviving evidence for a
     * junction-less request — a cancelled or rejected arrangement, whose
     * junction rows the actions delete. Same six keys, same casts as step 1.
     *
     * @return array{week: int, day: int, venue_id: int, start: string, end: string, count: int}|null
     */
    public function spanFromAnchor(?TimeSlot $anchor): ?array
    {
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

        $span = [
            'week' => (int) $slot->week_number,
            'day' => (int) $slot->day_of_week,
            'venue_id' => (int) $slot->venue_id,
            'start' => (string) $slot->start_time,
            'end' => (string) $slot->end_time,
        ];

        $strings = $this->stringsFromSpan($span, $semesterStart, $slot->venue);

        // the legacy key set only — the existing consumers (MyRequestHistory
        // :65-76, ApiReadController) read date/time/venue and are pinned.
        return ['date' => $strings['date'], 'time' => $strings['time'], 'venue' => $strings['venue']];
    }

    /**
     * SDD notif-new-request-ux (design §7's promotion): the span→strings body
     * — the 3rd request-display consumer (history, the API adapter, now the
     * notif composer) rides ONE format source. Keys: date/time (the legacy
     * SPACED en-dash form for the history consumers), week, day, venue
     * (room_code), venueName (room_name or null).
     *
     * @param  array{week: int, day: int, venue_id: int, start: string, end: string}|null  $span
     * @return array{date: ?string, time: ?string, week: ?int, day: ?string, venue: ?string, venueName: ?string}
     */
    public function stringsFromSpan(?array $span, Carbon $semesterStart, ?Venue $venue): array
    {
        if ($span === null || $venue === null) {
            return ['date' => null, 'time' => null, 'week' => null, 'day' => null, 'venue' => null, 'venueName' => null];
        }

        return [
            'date' => $semesterStart->copy()
                ->addWeeks((int) $span['week'] - 1)
                ->addDays((int) $span['day'])
                ->toDateString(),
            'time' => Carbon::parse((string) $span['start'])->format('H:i').' – '.Carbon::parse((string) $span['end'])->format('H:i'),
            'week' => (int) $span['week'],
            'day' => self::DAY_NAMES[(int) $span['day']] ?? '',
            'venue' => (string) $venue->room_code,
            'venueName' => $venue->room_name !== null ? (string) $venue->room_name : null,
        ];
    }

    /**
     * SDD notif-new-request-ux (design §3): the BATCH span read — ONE join
     * query for a whole request-id set, grouped into span()'s first/last
     * shape. Never called per-row on a 10 s poll. NOTE: the joined tables are
     * `replacement_request_slots`/`time_slots` — neither contains the
     * substring `replacement_requests` (the ONE-QUERY law test counts the
     * requests-mentioning SQL; verified in the design §3 [query budget]).
     *
     * @param  array<int, int>  $requestIds
     * @return array<int, array{week: int, day: int, venue_id: int, start: string, end: string, count: int}>
     */
    public function spansForRequests(array $requestIds): array
    {
        if ($requestIds === []) {
            return [];
        }

        $rows = DB::table('replacement_request_slots')
            ->join('time_slots', 'replacement_request_slots.time_slot_id', '=', 'time_slots.id')
            ->whereIn('replacement_request_slots.replacement_request_id', array_map('intval', $requestIds))
            ->orderBy('time_slots.start_time')
            ->get([
                'replacement_request_slots.replacement_request_id',
                'time_slots.week_number',
                'time_slots.day_of_week',
                'time_slots.venue_id',
                'time_slots.start_time',
                'time_slots.end_time',
            ]);

        return $rows
            ->groupBy('replacement_request_id')
            ->map(function ($group): array {
                return [
                    'week' => (int) $group->first()->week_number,
                    'day' => (int) $group->first()->day_of_week,
                    'venue_id' => (int) $group->first()->venue_id,
                    'start' => (string) $group->first()->start_time,
                    'end' => (string) $group->last()->end_time,
                    'count' => $group->count(),
                ];
            })
            ->all();
    }
}
