<?php

namespace App\Concerns;

use App\Models\ClassException;
use App\Models\ClassSession;
use App\Models\Cohort;
use App\Models\ReplacementRequest;
use App\Models\ReplacementRequestSlot;
use App\Models\Semester;
use App\Services\ReplacementRequestMapper;
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

        $exceptionKeys = [];
        foreach ($exceptions as $e) {
            $exceptionKeys[$e->class_session_id.':'.$e->week_number] = true;
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

        $out = [];
        for ($w = 1; $w <= $weeks; $w++) {
            $events = [];

            foreach ($sessions as $s) {
                if (isset($exceptionKeys[$s->id.':'.$w])) {
                    continue; // cancelled / released for this week (FR 2.16, D7)
                }

                $pending = $pendingByMakeupSlot[$s->id.':'.$w] ?? null;
                $pendingOccurrence = $pendingByOccurrence[$s->id.':'.$w] ?? null;
                $approved = null;
                foreach ($approvedByWeek[$w] ?? [] as $r) {
                    if ($r->class_session_id === $s->id) {
                        $approved = $r;
                        break;
                    }
                }

                if ($approved !== null) {
                    // FR 4.11: occurrence moved — original slot excluded, new slot added below.
                    continue;
                }

                $ev = $baseBySession[$s->id];
                if ($pending !== null) {
                    // USER DIRECTIVE 2026-10-17: the amber block's shape, day and
                    // venue come from the PROPOSED slots — the WHOLE run
                    // (ANCHOR..anchor+span−1 via the junction row count), not
                    // just the anchor's single 30-min pair.
                    $slot = $pending->replacementTimeSlot;
                    $spanSlots = ReplacementRequestSlot::query()
                        ->where('replacement_request_id', $pending->id)
                        ->count();
                    if ($slot !== null) {
                        $ev['di'] = (int) $slot->day_of_week;
                        $ev['start'] = $this->slotIndex((string) $slot->start_time);
                        $ev['end'] = $this->slotIndex((string) $slot->start_time) + max(1, $spanSlots) - 1;
                        $ev['venue'] = (string) $slot->venue->room_code;
                    }
                    $ev['status'] = 'pending';
                    $ev['requestId'] = $pending->id;
                    $ev['requestedAt'] = $pending->submitted_at->format('d M Y, h:i A');
                    $proposer = $pending->proposer;
                    $ev['requestedBy'] = $proposer !== null ? $proposer->name : '';
                    $events[] = $ev;

                    continue; // the session's own normal block is consumed by the amber block this week
                }

                // the natural red occurrence gains the.Proposed hint (option A):
                // TEXT INSIDE the block — the blade's buildReplacementNote
                // renders "(Proposed ⏳)" for events carrying proposed = true.
                if ($pendingOccurrence !== null) {
                    $ev['proposed'] = true;
                }
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
