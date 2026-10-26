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
        $start = Carbon::parse($semester !== null ? $semester->start_date : now()->toDateString());
        $weeks = $this->timelineWeeks();
        $end = $start->copy()->addWeeks($weeks - 1)->addDays(6);

        $code = $semester !== null ? $semester->semester_code : '';

        return [
            'label' => $code,
            'startDate' => $start->toDateString(),
            'endDate' => $end->toDateString(),
            'weeks' => $weeks,
            'chipText' => $code.' · '.$start->format('d-M-Y').' ~ '.$end->format('d-M-Y'),
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
        $start = Carbon::parse($semester !== null ? $semester->start_date : now()->toDateString());
        $today = today()->toDateString();
        $out = [];

        for ($w = 1; $w <= $weeks; $w++) {
            $monday = $start->copy()->addWeeks($w - 1);
            $days = [];
            foreach (['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $i => $abbr) {
                $dt = $monday->copy()->addDays($i);
                $days[] = [
                    'abbr' => $abbr,
                    'date' => $dt->format('d M Y'),
                    'sunday' => $i === 6,
                    'today' => $dt->toDateString() === $today,
                ];
            }
            $out[] = [
                'label' => 'Week '.$w,
                'range' => $monday->format('d M Y').' ~ '.$monday->copy()->addDays(6)->format('d M Y'),
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

        // Carbon 3: signed diff — positive when today is after the semester start.
        $days = (int) $semester->start_date->startOfDay()->diffInDays(today());
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
        $startDate = $semester !== null ? $semester->start_date : Carbon::parse(now()->toDateString());

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

            foreach ($sessions as $s) {
                // cancelled-blocks-stay-visible (user law 2026-10-27): the
                // exception'd pair KEEPS its block — original slot, red,
                // `(Cancelled)` — until the PL approves the proposed
                // replacement (then the D7 discipline applies: the original
                // vanishes, the landing block renders in its own week).
                if (isset($exceptionRow[$s->id.':'.$w])) {
                    if (isset($approvedByPair[$s->id.':'.$w])) {
                        continue; // law ④ — an approved release + the D-P10
                        // landing block; nothing renders here
                    }

                    /* laws ①/③: the cancelled block STAYS — original slot,
                       `event-conflict` (the client maps the status), the
                       label rides for the home badge, the Proposed hint for
                       a pending proposal, the D-P4 fact so the popup's
                       Replace-Now handoff stays available (a pending or
                       approved pair yields a NULL fact — the handled map).
                       ALWAYS continue: the tail push can never double-fire,
                       and the same-week amber makeup rides FROM HERE. */
                    $ev = $baseBySession[$s->id];
                    $ev['status'] = 'cancelled';
                    $ev['cancelledReason'] = ReasonLabel::from(
                        (string) $exceptionRow[$s->id.':'.$w]->reason);
                    if (isset($pendingByOccurrence[$s->id.':'.$w])) {
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
                        $events[] = $this->buildAmberEvent(
                            $baseBySession[$s->id], $requestedMakeup);
                    }

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

                $ev = $baseBySession[$s->id];
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

                    // USER DIRECTIVE 2026-10-17: the amber block's shape, day and
                    // venue come from the PROPOSED slots — the WHOLE run
                    // (ANCHOR..anchor+span−1 via the junction row count), not
                    // just the anchor's single 30-min pair. Hoisted into
                    // buildAmberEvent (cancelled-blocks-stay-visible): the
                    // exception'd branch emits the same shape for a same-week
                    // makeup.
                    $events[] = $this->buildAmberEvent($baseBySession[$s->id], $pending);

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

                $ev['status'] = 'replacement';
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
            }

            $out[$w - 1] = $events;
        }

        return $out;
    }

    /**
     * Venue-restriction conflict (VENUE-RESTRICTIONS.md, SDD b005-diploma-conflict):
     * a session conflicts when its venue is B005 AND any cohort belongs to a
     * D* (Diploma) programme. Derived at render time — the DB state machine
     * stays 3-state (FR 4.11); booking-time enforcement of the full rule set
     * is Slice B scope. Protected: shared with ReplacementHome (2.2).
     */
    protected function venueRestrictionConflict(ClassSession $s): bool
    {
        return $s->venue !== null
            && $s->venue->room_code === 'B005'
            && $s->cohorts->contains(fn ($c) => str_starts_with((string) $c->programme->programme_code, 'D'));
    }

    /**
     * @return array<string, mixed> Protected: shared with ReplacementHome (2.2).
     */
    /**
     * The amber make-up block (USER DIRECTIVE 2026-10-17 / D-P10), hoisted
     * from the pending path (cancelled-blocks-stay-visible): the shape, day
     * and venue come from the PROPOSED slot — the whole junction run. The
     * cancelled-block branch reuses it for a same-week makeup.
     *
     * @param  array<string, mixed>  $base
     * @param  ReplacementRequest  $pending
     * @return array<string, mixed>
     */
    protected function buildAmberEvent(array $base, $pending): array
    {
        $slot = $pending->replacementTimeSlot;
        $spanSlots = ReplacementRequestSlot::query()
            ->where('replacement_request_id', $pending->id)
            ->count();
        $evA = $base;
        if ($slot !== null) {
            $evA['di'] = (int) $slot->day_of_week;
            $evA['start'] = $this->slotIndex((string) $slot->start_time);
            $evA['end'] = $this->slotIndex((string) $slot->start_time) + max(1, $spanSlots) - 1;
            $evA['venue'] = (string) $slot->venue->room_code;
        }
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
            'name' => $s->module->module_name,
            'type' => $s->session_type,
            'venue' => $s->venue->room_code,
            'lecturer' => $s->lecturer !== null ? $s->lecturer->name : '—',
            'cohort' => $cohortLabel,
            'cohorts' => $cohorts->map(fn ($c) => $this->cohortCode($c))->all(),
            'studentCount' => (int) $cohorts->sum('student_count'),
            'status' => $this->venueRestrictionConflict($s) ? 'conflict' : 'normal',
            'remarks' => '',
        ];
    }
}
