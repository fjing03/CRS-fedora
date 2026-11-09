<?php

namespace App\Services;

use App\Models\ReplacementRequest;
use App\Models\Semester;
use App\Models\TimeSlot;
use App\Models\Venue;
use App\Support\LocalTime;
use App\Support\WorkingHours;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * THE shared request-row mapper (SDD span-aware-approval-flow — design D5 /
 * spec display-reads R3; the §10.0.6 promote-on-3rd-duplicate extraction).
 *
 * Owns the computations that the row readers used to duplicate, now used by
 * the PL queue, the JSON API, the notification composer and the merged
 * My Replacements page:
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

        /* SDD local-calendar-rules (2026-11-08): compare CAMPUS dates, not the
           UTC clock — between 00:00 and 08:00 MYT `today()` is yesterday, so a
           class that has already happened kept reading Approved. String compare:
           no midnight, no zone offset, no off-by-one. */
        return Carbon::parse($date)->toDateString() < LocalTime::dateString()
            ? 'completed'
            : 'approved';
    }

    /**
     * replacement-merge-one-table (M5/T3, 2026-11-04) — THE six-state vocabulary.
     * Pure, no queries: the page never decides a status, a label or a suffix, so
     * the badge, the card, the tab title and the sentence cannot disagree.
     *
     * `<n>` uses the campus today (LocalTime::today()) — the same calendar
     * `effectiveStatus()` compares against — so "Completed — 1 day ago" can never contradict the
     * status that produced it (a browser in another timezone would).
     *
     * @return array{label: string, short: string, sentence: string, tip: string}
     */
    public function statusVocabulary(?string $status, ?string $date, string $audience = 'lecturer', ?string $deadline = null): array
    {
        return [
            /* 2026-11-09 (student-class-replacements, design §2): the audience now
               reaches the LABEL too, so a page asks once. The default keeps every
               existing caller byte-identical. */
            'label' => $this->statusLabel($status, $date, $audience),
            'short' => $this->statusShort($status),
            'sentence' => $this->statusSentence($status, $date),
            'tip' => $this->statusTip($status, $audience, $deadline),
        ];
    }

    /**
     * The badge text (M5). A `completed` row carries how long ago, when known.
     *
     * `$audience` (2026-11-09, `student-class-replacements` §2) changes ONE word:
     * a student reads `Awaiting Arrangement` where a lecturer reads
     * `Need to Arrange` — the staff phrasing is an instruction, and the student
     * page is read-only. Every other label is shared on purpose, so the two pages
     * can never disagree about what a state MEANS.
     */
    public function statusLabel(?string $status, ?string $date, string $audience = 'lecturer'): string
    {
        if ($status === null) {
            return $audience === 'student' ? 'Awaiting Arrangement' : 'Need to Arrange';
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
     *
     * `$audience` (request-approval-ui 1f/3B, 2026-11-04): M5's copy is written
     * for the lecturer ("your request"); the PL queue reads the same states from
     * the other side of the decision. One helper, two voices — the lecturer's
     * strings are byte-identical to the approved M5 text.
     */
    public function statusTip(?string $status, string $audience = 'lecturer', ?string $deadline = null): string
    {
        /* Auto-reject rule (user brief 2026-11-06): a PENDING row states when the
           rule fires. The deadline is computed by the caller (it owns the
           semester start), so this stays a pure formatter. Two LINES, not HTML:
           the shared `data-tip` engine renders textContent (and pre-line for a
           `\n`), so a `<br>` would print literally. */
        $plPendingTail = $deadline === null
            ? null
            : 'If you have not Approved it by '.$deadline.', it will be Automatically Rejected.';

        /* Lecturer arm, copy per the 2026-11-06 UI update ("Pending Status
           Tooltip"): the sentence now NAMES the rule, the 3-working-hour
           requirement and the consequence — the deadline sits in the same
           parentheses the user's brief shows, and it is the row's own computed
           value (proposed start − 3 working hours on the WorkingHours calendar,
           holidays included), never a second calculation. The three emphasised
           phrases ride CAPITALISATION because a data-tip is textContent: no
           `<b>`, no markup, nothing to escape. */
        $lecturerPendingTail = $deadline === null
            ? null
            : 'Auto-Reject Rule: If This Request Has Not Been Approved At Least 3 Working Hours ('.$deadline.') Before The Proposed Class Start Time, It Will Be AUTOMATICALLY REJECTED.';

        if ($audience === 'pl') {
            $tip = match ($status) {
                null => 'No request has been made for this class yet — it still needs arranging.',
                'pending' => 'This request is AWAITING your review and decision (Approve or Reject).',
                'approved' => 'You have APPROVED this request.',
                'rejected' => 'You have REJECTED this request.',
                'cancelled' => 'The lecturer CANCELLED this request.',
                'completed' => 'The replacement class has been CONDUCTED — this request is complete.',
                default => '',
            };

            return $status === 'pending' && $plPendingTail !== null ? $tip."\n".$plPendingTail : $tip;
        }

        /* 2026-11-09 (student-side UI update, grill Q4=B): the student history
           prints the SAME labels as the staff table, so it needs a tip in a voice
           that is not the proposer's — a student never submitted the request, and
           "Your request" would be a lie on their page. The labels stay identical;
           only the voice changes. */
        if ($audience === 'student') {
            $tip = match ($status) {
                null => 'No request has been made for this class yet.',
                'pending' => "This class's replacement has been REQUESTED and is AWAITING the Programme Leader's decision (Approve or Reject).",
                'approved' => 'The Programme Leader has APPROVED the requested replacement — attend the requested slot.',
                'rejected' => 'The Programme Leader has REJECTED the requested replacement.',
                'cancelled' => 'The replacement request was CANCELLED — the class still needs arranging.',
                'completed' => 'The replacement class has been CONDUCTED — this request is complete.',
                default => '',
            };

            return $status === 'pending' && $deadline !== null
                ? $tip."\n".'If the Programme Leader does not decide by '.$deadline.', this request is Automatically Rejected.'
                : $tip;
        }

        $tip = match ($status) {
            null => 'No request has been made for this class yet — it still needs arranging.',
            'pending' => "Your request has been Submitted and is AWAITING the Programme Leader's decision (Approve or Reject).",
            'approved' => 'The Programme Leader has APPROVED your request.',
            'rejected' => 'The Programme Leader has REJECTED your request.',
            'cancelled' => 'You MANUALLY CANCELLED this request.',
            'completed' => 'The replacement class has been CONDUCTED — this request is complete.',
            default => '',
        };

        return $status === 'pending' && $lecturerPendingTail !== null ? $tip."\n".$lecturerPendingTail : $tip;
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

        // SDD local-calendar-rules: the campus calendar (both sides read as MYT days)
        $days = (int) abs(LocalTime::daysSince($date));

        return $days <= 0 ? 'today' : ($days === 1 ? '1 day ago' : $days.' days ago');
    }

    /**
     * The canonical PL-queue row (frozen template's `requests` §2.13 shape).
     *
     * @return array<string, mixed>
     */
    public function toRow(ReplacementRequest $r, Carbon $semesterStart, string $requestedBucket, ?ValidityResult $validity = null, string $audience = 'lecturer'): array
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
        $statusKey = $this->effectiveStatus($r, $semesterStart);
        $prettyStatus = ucfirst($statusKey);

        /* request-approval-ui 1f/3B (2026-11-04): the M5 vocabulary rides the
           row, so the PL page's badge, badge tip, pop-up row and status tab all
           read ONE source instead of a page-local 3-key map. `$audience` picks
           the voice for the tip only — the labels are the same on both sides. */
        $deadline = $statusKey === 'pending'
            ? $this->approvalDeadlineLabel($span['date'], $span['time'], (int) $r->semester_id)
            : null;

        $vocabulary = $this->statusVocabulary($statusKey, $identity['classDate'] ?? null, $audience, $deadline);

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
            // the shared M5 vocabulary (1f/3B) — the page never re-words a status
            'requestStatusLabel' => $vocabulary['label'],
            'requestStatusShort' => $vocabulary['short'],
            'requestStatusSentence' => $vocabulary['sentence'],
            'requestStatusTip' => $vocabulary['tip'],
            'rejectionReason' => $r->rejection_reason,
            'replacementDate' => $span['date'],
            'replacementTime' => $span['time'],
            'replacementVenue' => $span['venue'],
            // request-approval-ui 3C (2026-11-04): the room NAME beside the code
            // ("B103 — Tutorial Room"). The anchor slot is the span's first cell,
            // and both consumers eager-load `replacementTimeSlot.venue`, so this
            // costs no query. Stripped from /api/requests/my (see
            // ApiReadController::myRequests) — the API's key set is pinned.
            'replacementVenueName' => $r->replacementTimeSlot?->venue?->room_name,
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
            'courseName' => $session->module->displayName(),
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
            // request-approval-ui 3C (2026-11-04): the room NAME beside the code
            // in the pop-up's Original Class tab. View-only key — stripped from
            // /api/requests/my (its key set is pinned by a test).
            'venueName' => $session->venue?->room_name,
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

        return $this->spanFromAnchor($r->replacementTimeSlot, $r->replacement_span);
    }

    /**
     * span()'s step 2, extracted (replacement-merge-one-table T1, 2026-11-04) so
     * the batch caller reuses the body instead of copying it: the anchor slot
     * (`replacement_time_slot_id`) is the surviving evidence for a
     * junction-less request — a cancelled or rejected arrangement, whose
     * junction rows the release actions delete.
     *
     * SDD replacement-span-persistence D3 (user 2026-11-07): the anchor is ONE
     * 30-minute cell, so on its own it under-reported every multi-cell proposal
     * (request #24 read 30 min instead of 1 h, #37 30 min instead of 2 h). The
     * request now stores its block width, and `$span` grows the end from the
     * anchor's own start. A null `$span` (a row the backfill could not resolve)
     * keeps the old single-cell shape — no display moves without a value.
     *
     * @return array{week: int, day: int, venue_id: int, start: string, end: string, count: int}|null
     */
    public function spanFromAnchor(?TimeSlot $anchor, ?int $span = null): ?array
    {
        if ($anchor === null) {
            return null;
        }

        // 0, negative and null all mean "no stored width" → the anchor alone.
        $count = $span !== null && $span > 0 ? $span : 1;

        return [
            'week' => (int) $anchor->week_number,
            'day' => (int) $anchor->day_of_week,
            'venue_id' => (int) $anchor->venue_id,
            'start' => (string) $anchor->start_time,
            'end' => $count > 1
                ? Carbon::parse((string) $anchor->start_time)->addMinutes($count * 30)->format('H:i:s')
                : (string) $anchor->end_time,
            'count' => $count,
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
     * THE proposed replacement START as a display-clock instant (auto-reject rule,
     * user brief 2026-11-06). The date comes from the SAME week/day arithmetic the
     * row prints (`stringsFromSpan`), the time from the span's first cell — so the
     * deadline a tooltip promises and the moment the sweep compares against are
     * literally one computation.
     *
     * Null when the request has no readable span: the callers then omit the
     * deadline rather than inventing one.
     */
    public function proposedStart(ReplacementRequest $r, Carbon $semesterStart): ?Carbon
    {
        $slot = $this->displaySlot($r);

        if ($slot === null || (string) $slot->start_time === '') {
            return null;
        }

        $date = $semesterStart->copy()
            ->addWeeks((int) $slot->week_number - 1)
            ->addDays((int) $slot->day_of_week)
            ->toDateString();

        return Carbon::parse($date.' '.$slot->start_time, LocalTime::TZ);
    }

    /**
     * The badge tooltip's "Earliest Approval Time": the proposed start minus 3
     * working hours on the TARUMT Sabah calendar (Mon–Fri 08:30–17:30, semester
     * holidays excluded).
     *
     * Takes the row's ALREADY-RESOLVED `['date','time']` strings — the merged
     * page reads the junction once per render (a pinned law), so this helper must
     * not read it again. Null when the pair is missing.
     */
    public function approvalDeadlineLabel(?string $date, ?string $time, int $semesterId): ?string
    {
        if ($date === null || $time === null) {
            return null;
        }

        // `replacementStrings()` prints 'HH:MM – HH:MM'; the deadline walks back
        // from the SPAN'S FIRST cell, so only the left side matters.
        $start = trim(explode('–', $time)[0]);

        if ($start === '') {
            return null;
        }

        $startAt = Carbon::parse($date.' '.$start, LocalTime::TZ);
        $workingHours = new WorkingHours($this->semesterFor($semesterId));

        return LocalTime::stamp($workingHours->earliestApproval($startAt), 'D, j M Y, g:i A');
    }

    /** @var array<int, Semester|null> one holiday map per semester, not one per row */
    private array $semesterCache = [];

    private function semesterFor(int $semesterId): ?Semester
    {
        if (! array_key_exists($semesterId, $this->semesterCache)) {
            $this->semesterCache[$semesterId] = Semester::query()->find($semesterId);
        }

        return $this->semesterCache[$semesterId];
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

        // the three-key shape only: the readers pinned this key set (date /
        // time / venue) and `stringsFromSpan()` is the six-key source beside
        // it — that difference is why the two helpers exist.
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
     * The email schedule line — `Mon, 9 Nov 2026, 1:30 PM ~ 3:30 PM`
     * (audit #29). Display only: `stringsFromSpan()` keeps its ISO date and
     * 24-hour span for the page consumers.
     *
     * @param  array{week: int, day: int, start: string, end: string}|null  $span
     */
    public function mailSchedule(?array $span, Carbon $semesterStart): ?string
    {
        if ($span === null) {
            return null;
        }

        $date = $this->mailDate($span, $semesterStart);
        $time = $this->mailTime($span);

        return $date === '' || $time === '' ? null : $date.', '.$time;
    }

    /**
     * `Mon, 9 Nov 2026` — the date half of the email schedule line.
     *
     * @param  array{week: int, day: int}|null  $span
     */
    public function mailDate(?array $span, Carbon $semesterStart): string
    {
        if ($span === null) {
            return '';
        }

        return $semesterStart->copy()
            ->addWeeks((int) $span['week'] - 1)
            ->addDays((int) $span['day'])
            ->format('D, j M Y');
    }

    /**
     * `1:30 PM ~ 3:30 PM` — the time half of the email schedule line.
     *
     * @param  array{start: string, end: string}|null  $span
     */
    public function mailTime(?array $span): string
    {
        if ($span === null) {
            return '';
        }

        return Carbon::parse((string) $span['start'])->format('g:i A')
            .' ~ '.Carbon::parse((string) $span['end'])->format('g:i A');
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
