<?php

namespace App\Concerns;

use App\Models\ClassException;
use App\Models\ClassSession;
use App\Models\Cohort;
use App\Models\ReplacementRequest;
use App\Models\ReplacementRequestSlot;
use App\Models\Semester;
use App\Services\LectureSessionCatalog;
use App\Services\ReplacementRequestMapper;
use App\Services\TimetableEventIdentity;
use App\Support\LocalTime;
use App\Support\ReasonLabel;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Shared timetable-timeline builder for the Livewire timetable pages
 * (SDD wire-backend-into-refactored-ui — promoted on 3rd duplication
 * per CodingMAIN §10.0.6: MyTimetable / CohortTimetable / StudentMyTimetable).
 *
 * Produces the legacy JS event contract (di = day index 0-5, start/end =
 * 30-min slot indexes from 08:00, end inclusive) consumed by the shared
 * grid engine in public/js/ui-common.js, with real DB overlays:
 * class_exceptions exclude a (session, week); pending requests mark the
 * original occurrence; approved requests move the occurrence to its new
 * time slot (status 'replacement').
 *
 * SDD cancelled-slot-reuse (A11/A12 — design D5, specs S13–S16): every
 * event carries a stable `eventKey` (occ:<semester>:<session>:<week> for an
 * occurrence, request:<requestId> for a landing) from
 * {@see TimetableEventIdentity}, the SAME deduplication the
 * venue page shares. A cancelled HISTORY event needs the S13 discriminator
 * (current exception + non-maintenance class_cancelled audit for the exact
 * pair): a proven user-cancelled original stays visible whenever ANY
 * in-scope active event overlaps its week/day/time, while an approval-only
 * `official_event` stamp keeps today's suppression unchanged in all cases.
 */
trait ResolvesTimetableTimeline
{
    private ?Semester $timelineSemesterCache = null;

    protected function timelineSemester(): ?Semester
    {
        if ($this->timelineSemesterCache === null) {
            $this->timelineSemesterCache = Semester::active();
        }

        return $this->timelineSemesterCache;
    }

    protected function timelineWeeks(): int
    {
        $semester = $this->timelineSemester();

        return $semester !== null ? $semester->week_count : 14;
    }

    /**
     * @return array<string, mixed> MockData.semester-compatible payload
     */
    protected function semesterJs(): array
    {
        $semester = $this->timelineSemester();
        $start = Carbon::parse($semester !== null ? $semester->start_date : LocalTime::today());
        $weeks = $this->timelineWeeks();
        $end = $start->copy()->addWeeks($weeks - 1)->addDays(6);

        $code = $semester !== null ? $semester->semester_code : '';

        return [
            'label' => $code,
            'startDate' => $start->toDateString(),
            'endDate' => $end->toDateString(),
            'weeks' => $weeks,
            'chipText' => $code.' · '.$start->format('j M Y').' ~ '.$end->format('j M Y'),
        ];
    }

    /**
     * @return array<int, array<string, int|string>> MockData.holidays-compatible payload
     */
    protected function holidaysForJs(): array
    {
        $semester = $this->timelineSemester();
        if ($semester === null) {
            return [];
        }

        return $semester->holidays()->get(['week_number', 'day_of_week', 'label'])
            ->map(fn ($h) => [
                'week' => $h->week_number,
                'dayIndex' => $h->day_of_week,
                'label' => $h->label,
            ])
            ->all();
    }

    /**
     * Week scaffolds with day metadata for the grid engine.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function weekData(): array
    {
        $weeks = $this->timelineWeeks();
        $semester = $this->timelineSemester();
        $start = Carbon::parse($semester !== null ? $semester->start_date : LocalTime::today());
        $today = LocalTime::dateString();   // SDD local-calendar-rules: the campus date
        $out = [];

        for ($w = 1; $w <= $weeks; $w++) {
            $monday = $start->copy()->addWeeks($w - 1);
            $days = [];
            foreach (['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $i => $abbr) {
                $dt = $monday->copy()->addDays($i);
                $days[] = [
                    'abbr' => $abbr,
                    'date' => $dt->format('j M Y'),
                    'sunday' => $i === 6,
                    'today' => $dt->toDateString() === $today,
                ];
            }
            $out[] = [
                'label' => 'Week '.$w,
                'range' => $monday->format('j M Y').' ~ '.$monday->copy()->addDays(6)->format('j M Y'),
                'days' => $days,
            ];
        }

        return $out;
    }

    protected function currentTimelineWeekIndex(): int
    {
        $semester = $this->timelineSemester();
        if ($semester === null) {
            return 0;
        }

        /* SDD local-calendar-rules (2026-11-08): the campus calendar decides which
           week it is. With the UTC clock, a Monday before 08:00 MYT still read as
           Sunday → the page opened one week low. */
        $days = LocalTime::daysSince($semester->start_date);
        $idx = intdiv($days, 7);

        return max(0, min($this->timelineWeeks() - 1, $idx));
    }

    protected function slotIndex(string $time): int
    {
        [$h, $m] = array_map('intval', explode(':', substr($time, 0, 5)));

        return ($h - 8) * 2 + intdiv($m, 30);
    }

    protected function cohortCode(?Cohort $cohort): string
    {
        if ($cohort === null || $cohort->programme === null) {
            return '—';
        }

        return $cohort->programme->programme_code
            .$cohort->current_year.'(S'.$cohort->semester.')G'.$cohort->tutorial_group;
    }

    /**
     * Build the per-week event map (0-indexed integer keys) consumed by the grid engine.
     *
     * cancelled-slot-reuse A11/A12 (D5, S13–S16): a two-pass build — the
     * week's in-scope ACTIVE events first, then the cancelled-history pass
     * decides each exception'd pair against the S13 provenance + S16 overlap
     * evidence — and every event leaves deduplicated by `eventKey`.
     *
     * @param  Collection<int, ClassSession>  $sessions  recurring base sessions (module/venue/cohorts/lecturer eager-loaded)
     * @param  Collection<int, ReplacementRequest>  $requests  pending+approved requests touching those sessions (proposer + replacementTimeSlot.venue eager-loaded)
     * @param  Collection<int, ClassException>  $exceptions  class_exceptions rows
     * @param  string|null  $cohortScopeCode  optional fixed cohort label (CohortTimetable/StudentMyTimetable)
     * @return array<int, array<int, array<string, mixed>>>
     */
    protected function buildEventsByWeek(
        Collection $sessions,
        Collection $requests,
        Collection $exceptions,
        ?string $cohortScopeCode = null,
    ): array {
        $weeks = $this->timelineWeeks();
        $semester = $this->timelineSemester();
        $startDate = $semester !== null ? $semester->start_date : LocalTime::today();

        /* cancelled-blocks-stay-visible (user law 2026-10-27): the keyed
           ROW map replaces the boolean — presence AND the reason both read
           from it. The discriminator stays READ-TIME: a pair is skipped iff
           an APPROVED replacement request covers it (a release stamp, D7,
           always coexists with its approved pair; a user cancel never
           does) — no check-value regime, no data migration. */
        $exceptionRow = [];
        foreach ($exceptions as $e) {
            $exceptionRow[$e->class_session_id.':'.$e->week_number] = $e;
        }
        $approvedByPair = [];
        foreach ($requests as $r) {
            if ($r->status === 'approved') {
                $approvedByPair[$r->class_session_id.':'.$r->week_number] = true;
            }
        }

        /* USER DIRECTIVE (2026-10-17 "own pending make-up block"): the pending
           badge moves to the PROPOSED make-up slot, not the original conflict
           occurrence. Two keyed maps carry the split:
           - pendingByMakeupSlot[session:slotWeek]  — renders the AMBER block at
             the reservation's own day/time/venue (status pending);
           - pendingByOccurrence[session:occWeek]   — the natural red
             conflict/holiday occurrence gains the "Proposed" hint suffix
             (option A — text inside the block).
           Cancelled/rejected requests never enter either map — the natural
           state + NO hint is automatic. */
        $pendingByMakeupSlot = [];
        $pendingByOccurrence = [];
        $approvedByWeek = [];
        foreach ($requests as $r) {
            if ($r->status === 'pending') {
                $pendingByOccurrence[$r->class_session_id.':'.$r->week_number] = $r;
                $makeupSlot = $r->replacementTimeSlot;
                if ($makeupSlot !== null) {
                    $pendingByMakeupSlot[$r->class_session_id.':'.$makeupSlot->week_number] = $r;
                }
            } elseif ($r->status === 'approved') {
                // Full-block approval (SDD span-aware-approval-flow): the
                // approved event LANDS AT THE REPLACEMENT WEEK (the slot's
                // own week), NOT the request's original week — the original
                // week's occurrence releases via the D7 exception stamp.
                $approvedSlot = $r->replacementTimeSlot;
                $approvedWeekKey = $approvedSlot !== null ? (int) $approvedSlot->week_number : (int) $r->week_number;
                $approvedByWeek[$approvedWeekKey][] = $r;
            }
        }

        /* cancelled-slot-reuse A11 (spec S13): the batched AUDIT half of the
           discriminator — which of these sessions carry a NON-maintenance
           `class_cancelled` audit naming an exact (session, week) pair. ONE
           read per render; the exception row is the other half. */
        $cancelledAudits = TimetableEventIdentity::userCancelledAuditPairs($sessions->pluck('id'));
        $semesterId = (int) ($semester->id ?? 0);

        $baseBySession = [];
        foreach ($sessions as $s) {
            $baseBySession[$s->id] = $this->baseEvent($s, $cohortScopeCode);
        }

        // occurrence-week-pin-truthfulness D-P4: ONE batched fact context per
        // render — every rendered occurrence stamps its OWN pair's verdict
        // (null for a normal week; exception-week pairs yield NO event — the
        // loop's FR 2.16 skip stays — their facts verify at the catalog level).
        $catalog = app(LectureSessionCatalog::class);
        $factContext = $catalog->factContext(Collection::wrap($sessions));

        $out = [];
        for ($w = 1; $w <= $weeks; $w++) {
            $events = [];
            /* cancelled-slot-reuse A12 (spec S16): the in-scope ACTIVE events
               of this week (natural occurrences, pending make-ups, approved
               landings) — the overlap evidence that keeps a PROVEN
               user-cancelled original's history visible. Two-pass build:
               active events first, then the cancelled-history pass reads
               their intervals. */
            $activeIntervals = [];
            $rememberCancelled = [];

            foreach ($sessions as $s) {
                // cancelled-blocks-stay-visible (user law 2026-10-27): the
                // exception'd pair KEEPS its block — original slot, red,
                // `(Cancelled)` — until the PL approves the proposed
                // replacement (then the D7 discipline applies: the original
                // vanishes, the landing block renders in its own week).
                // cancelled-slot-reuse refines the approved leg (S16): a
                // PROVEN user-cancelled original stays visible when any
                // in-scope active event overlaps it; an approval-only stamp
                // keeps today's suppression. The decision is deferred to the
                // second pass so it sees the WHOLE week's active intervals.
                if (isset($exceptionRow[$s->id.':'.$w])) {
                    $rememberCancelled[] = [$s, $exceptionRow[$s->id.':'.$w], $s->id.':'.$w];

                    continue;
                }

                $pending = $pendingByMakeupSlot[$s->id.':'.$w] ?? null;
                $pendingOccurrence = $pendingByOccurrence[$s->id.':'.$w] ?? null;

                // occurrence-week-pin-truthfulness D-P10 (CHOICE A): the
                // landing week keeps the class's OWN natural block — the
                // replaced week's suppression belongs to the D7 stamp ALONE
                // (the exceptionKeys guard above). The approved receipt
                // renders in its own junction cell below the loop; NO
                // landing-week natural-block swallowing survives.

                $ev = TimetableEventIdentity::tag($baseBySession[$s->id],
                    TimetableEventIdentity::occurrenceKey($semesterId, (int) $s->id, $w));
                if ($pending !== null) {
                    // occurrence-week-pin-truthfulness D-P10 (CHOICE A): the
                    // natural block KEEPS its own cell; the amber proposal
                    // rides its own landing cell. The Proposed ⏳ hint stays
                    // on the natural conflict occurrence's own cell (the
                    // pendingByOccurrence map + buildReplacementNote — no
                    // client edits). At an exact same-hour overlap the grid's
                    // slotMap last-write-wins shows the AMBER (the push-order
                    // covenant: natural FIRST, amber SECOND).
                    $events[] = $ev;
                    $activeIntervals[] = ['di' => $ev['di'], 'start' => $ev['start'], 'end' => $ev['end']];

                    // USER DIRECTIVE 2026-10-17: the amber block's shape, day and
                    // venue come from the PROPOSED slots — the WHOLE TRUE span
                    // (live junction min/max, the mapper's anchor fallback), not
                    // just the anchor's single 30-min pair. Hoisted into
                    // buildAmberEvent (cancelled-blocks-stay-visible): the
                    // exception'd branch emits the same shape for a same-week
                    // makeup.
                    $amber = $this->buildAmberEvent($baseBySession[$s->id], $pending);
                    $events[] = $amber;
                    $activeIntervals[] = ['di' => $amber['di'], 'start' => $amber['start'], 'end' => $amber['end']];

                    continue;
                }

                // the natural red occurrence gains the.Proposed hint (option A):
                // TEXT INSIDE the block — the blade's buildReplacementNote
                // renders "(Proposed ⏳)" for events carrying proposed = true.
                if ($pendingOccurrence !== null) {
                    $ev['proposed'] = true;
                }

                // occurrence-week-pin-truthfulness D-P4: the EXACT-PAIR stamp —
                // the popup's Replace-Now gate reads THIS verdict (the pair's
                // week RIDES the stamp so the handoff can never drift); null
                // for a normal week = no button, no handoff.
                $fact = $catalog->occurrenceFact($s, $w, $factContext);
                $ev['occurrenceFact'] = $fact !== null
                    ? ['week' => $w, 'kind' => $fact['kind'], 'reason' => $fact['reason']]
                    : null;
                $events[] = $ev;
                $activeIntervals[] = ['di' => $ev['di'], 'start' => $ev['start'], 'end' => $ev['end']];
            }

            foreach ($approvedByWeek[$w] ?? [] as $r) {
                $session = $sessions->firstWhere('id', $r->class_session_id);
                $slot = $r->replacementTimeSlot;
                if ($session === null || $slot === null || ! isset($baseBySession[$session->id])) {
                    continue;
                }

                $ev = $baseBySession[$session->id];
                $ev['di'] = $slot->day_of_week;
                $ev['start'] = $this->slotIndex($slot->start_time);
                $ev['end'] = $this->slotIndex($slot->end_time) - 1;
                $ev['venue'] = $slot->venue->room_code;

                // Full-block span read (SDD span-aware-approval-flow D4 ①,
                // grill Q3=A): an APPROVED request's replacement block covers
                // the WHOLE junction run (anchor..anchor+span−1), not the
                // anchor's 30 min. Data-driven: junction-less rows keep the
                // legacy anchor shape byte-identically.
                $span = app(ReplacementRequestMapper::class)->span($r);
                if ($span !== null && $span['count'] > 1) {
                    $ev['start'] = $this->slotIndex($span['start']);
                    $ev['end'] = $this->slotIndex($span['end']) - 1;
                }

                $ev = TimetableEventIdentity::tag($ev,
                    TimetableEventIdentity::requestKey((int) $r->id));
                $ev['status'] = 'replacement';
                /* mytimetable-jump-highlight (user, 2026-11-04): the replacement
                   record's OWN id rides the event, so the client can find this
                   exact block. `sessionId` cannot do it — the class's natural
                   blocks share it — and name/date/week are not identities. */
                $ev['requestId'] = (int) $r->id;
                // USER 2026-10-19 (venue date parity): the note's date reads the
                // ORIGINAL class's own occurrence (request week + the session's
                // day — venue page's keying), not the replacement's landing week.
                $ev['remarks'] = $startDate->copy()
                    ->addWeeks((int) $r->week_number - 1)
                    ->addDays((int) $session->day_of_week)
                    ->format('d-M-Y');
                // The modal's Original rows key on the ORIGINAL's own venue —
                // the block above $ev['venue'] now carries the LANDING room,
                // which differs when a request moved venues.
                $ev['originalVenue'] = (string) $session->venue->room_code;
                $ev['originalVenueName'] = (string) ($session->venue->room_name ?? '');
                // The ORIGINAL's own clock (14:30 – 15:30 for B002's #10) — the
                // block's start/end carry the LANDING junction's span.
                $ev['originalStartTime'] = substr((string) $session->start_time, 0, 5);
                $ev['originalEndTime'] = substr((string) $session->end_time, 0, 5);
                $events[] = $ev;
                $activeIntervals[] = ['di' => $ev['di'], 'start' => $ev['start'], 'end' => $ev['end']];
            }

            /* cancelled-slot-reuse A12 — the SECOND pass (S13/S16): the
               cancelled-history verdict per remembered pair. A PROVEN user
               cancellation (current exception + non-maintenance
               class_cancelled audit) renders whenever NO approved pair
               releases it, or when ANY in-scope active event overlaps its
               original week/day/time (the exact/partial-reuse case — its own
               approved landing included). An approval-only `official_event`
               stamp (no audit) keeps today's law byte-identically: released
               pair = suppressed, otherwise the cancelled block renders.
               Pending proposals never suppress. Withdrawn/rejected/
               auto-rejected requests are simply absent (they remove the
               landing only), so a suppressed-again released pair falls back
               to the no-approved-pair leg and the history stays. */
            foreach ($rememberCancelled as [$s, $exception, $pairKey]) {
                $prov = TimetableEventIdentity::provenance($exception, isset($cancelledAudits[$pairKey]));
                $approvedPair = isset($approvedByPair[$pairKey]);
                $base = $baseBySession[$s->id];

                if ($approvedPair && ! (
                    $prov === TimetableEventIdentity::PROVENANCE_USER_CANCELLED
                    && TimetableEventIdentity::overlapsOriginal(
                        $activeIntervals, (int) $s->day_of_week, (int) $base['start'], (int) $base['end']))
                ) {
                    continue;   // the handled-original suppression, unchanged
                }

                $ev = TimetableEventIdentity::tag($base,
                    TimetableEventIdentity::occurrenceKey($semesterId, (int) $s->id, $w));
                $ev['status'] = 'cancelled';
                $ev['cancelledReason'] = ReasonLabel::from(
                    (string) $exception->reason);
                /* S13's honest projection: the payload SAYS which evidence
                   grade produced this history, so missing/contradictory
                   provenance is reported, never synthesised. */
                $ev['cancellationProvenance'] = $prov;
                if (isset($pendingByOccurrence[$pairKey])) {
                    $ev['proposed'] = true;   // "(Proposed ⏳)" note law
                }
                $fact = $catalog->occurrenceFact($s, $w, $factContext);
                $ev['occurrenceFact'] = $fact !== null
                    ? ['week' => $w, 'kind' => $fact['kind'], 'reason' => $fact['reason']]
                    : null;
                $events[] = $ev;

                $requestedMakeup = $pendingByMakeupSlot[$s->id.':'.$w] ?? null;
                if ($requestedMakeup !== null) {
                    // the existing amber emission (D-P10), same-week:
                    // re-shape the natural block onto the slot's own
                    // day/time/venue. (A different-week makeup already
                    // rides its own week's loop pass.)
                    $amber = $this->buildAmberEvent($base, $requestedMakeup);
                    $events[] = $amber;
                    $activeIntervals[] = ['di' => $amber['di'], 'start' => $amber['start'], 'end' => $amber['end']];
                }
            }

            /* cancelled-slot-reuse A11 (D5): deduplicate by eventKey before
               the events leave the server — the client and the summary
               calculations consume unique identities. */
            $out[$w - 1] = TimetableEventIdentity::dedupeSameKey($events);
        }

        return $out;
    }

    /**
     * Venue-restriction conflict (VENUE-RESTRICTIONS.md, SDD
     * b005-diploma-conflict): REPEALED 2026-10-27 (SDD
     * b005-diploma-allowed, the user's ruling — Diploma cohorts allowed).
     * The method is GONE; the retired payload branch rode it at :485 (the
     * timeline renders 'normal' unconditionally now), and the venue grid's
     * severity-3 arm at VenueTimetable dies with it.
     */
    /**
     * @return array<string, mixed> Protected: shared with ReplacementHome (2.2).
     */
    /**
     * The amber make-up block (USER DIRECTIVE 2026-10-17 / D-P10), hoisted
     * from the pending path (cancelled-blocks-stay-visible): the shape, day
     * and venue come from the PROPOSED slot — the whole TRUE span (live
     * junction min/max, the mapper's anchor fallback for junction-less
     * rows; SDD cancelled-slot-reuse A12/S14). The cancelled-block branch
     * reuses it for a same-week makeup.
     *
     * @param  array<string, mixed>  $base
     * @param  ReplacementRequest  $pending
     * @return array<string, mixed>
     */
    protected function buildAmberEvent(array $base, $pending): array
    {
        $slot = $pending->replacementTimeSlot;
        $evA = $base;
        if ($slot !== null) {
            $evA['di'] = (int) $slot->day_of_week;
            /* cancelled-slot-reuse A12 (spec S14): the pending landing keeps
               its TRUE full span — the live junction's min/max read via the
               shared mapper, its anchor+`replacement_span` fallback for
               junction-less rows — never a bare 30-minute anchor. The span is
               applied only when it agrees with the anchor's week/day (a
               drifted receipt falls back to the anchor shape). */
            $span = app(ReplacementRequestMapper::class)->span($pending);
            if ($span !== null
                && (int) $span['week'] === (int) $slot->week_number
                && (int) $span['day'] === (int) $slot->day_of_week) {
                $evA['start'] = $this->slotIndex((string) $span['start']);
                $evA['end'] = $this->slotIndex((string) $span['end']) - 1;
            } else {
                $spanSlots = ReplacementRequestSlot::query()
                    ->where('replacement_request_id', $pending->id)
                    ->count();
                $evA['start'] = $this->slotIndex((string) $slot->start_time);
                $evA['end'] = $this->slotIndex((string) $slot->start_time) + max(1, $spanSlots) - 1;
            }
            $evA['venue'] = (string) $slot->venue->room_code;
        }
        $evA = TimetableEventIdentity::tag($evA,
            TimetableEventIdentity::requestKey((int) $pending->id));
        $evA['status'] = 'pending';
        $evA['requestId'] = $pending->id;
        $evA['requestedAt'] = app(ReplacementRequestMapper::class)->stampDisplay($pending->submitted_at, 'd M Y, h:i A');   // MYT (SDD occurrence-week-pin-truthfulness popup-accuracy rider)
        $proposer = $pending->proposer;
        $evA['requestedBy'] = $proposer !== null ? $proposer->name : '';

        return $evA;
    }

    /**
     * The natural event shape for one session ( timetable-data-wiring).
     *
     * @return array<string, mixed>
     */
    /**
     * The SHORT venue label law (mock-statics-die G1(b), 2026-10-27 — the
     * ReasonLabel/titleCase display-law precedent, server-stamped):
     * trims the trailing room-code suffix, then swaps the lab family's
     * full word — `Computer Laboratory 5` → `Computer Lab 5`;
     * `Tutorial Room B002` → `Tutorial Room`.
     */
    protected static function shortVenueName(?string $roomName, string $roomCode): string
    {
        $base = (string) preg_replace('/\s*'.preg_quote($roomCode, '/').'\s*$/i', '', (string) $roomName);

        return str_replace('Computer Laboratory', 'Computer Lab', $base) !== ''
            ? str_replace('Computer Laboratory', 'Computer Lab', $base)
            : (string) $roomName;
    }

    /**
     * @return array<string, mixed>
     */
    protected function baseEvent(ClassSession $s, ?string $cohortScopeCode = null): array
    {
        $cohorts = $s->cohorts;
        $cohortLabel = $cohortScopeCode ?? ($cohorts->count() > 1
            ? $cohorts->map(fn ($c) => $this->cohortCode($c))->implode(' + ')
            : $this->cohortCode($cohorts->first()));

        return [
            'id' => $s->id,
            'di' => $s->day_of_week,
            'start' => $this->slotIndex($s->start_time),
            'end' => $this->slotIndex($s->end_time) - 1,
            'code' => $s->module->module_code,
            'name' => $s->module->displayName(),
            'type' => $s->session_type,
            'venue' => $s->venue->room_code,
            /* 2026-11-05 (Cancel this Class? UI/UX update): the modal's facts
               box prints `CODE — Name`, so the event carries the room name the
               same nullable way ReplacementRequestMapper does (null, never ''). */
            'venueName' => $s->venue?->room_name,
            'lecturer' => $s->lecturer !== null ? $s->lecturer->name : '—',
            'cohort' => $cohortLabel,
            'cohorts' => $cohorts->map(fn ($c) => $this->cohortCode($c))->all(),
            // the per-cohort student-count breakdown (BMSE3153-class-info user
            // law 2026-10-27): the modal renders `A + B = total` for multi-
            // cohort classes. POSITIONAL — the mock-data convention
            // (mock-data.js:252 `studentCounts: [28, 22]` pairs with the
            // `cohorts` array by index); counts come from cohorts.student_count.
            'studentCounts' => $s->cohorts
                ->map(fn ($c) => (int) ($c->student_count ?? 0))
                ->values()
                ->all(),
            'studentCount' => (int) $cohorts->sum('student_count'),
            'status' => 'normal',
            'remarks' => '',
        ];
    }
}
