<?php

namespace App\Livewire;

use App\Concerns\ResolvesTimetableTimeline;
use App\Models\Cohort;
use App\Models\TimeSlot;
use App\Models\Venue;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Venue Timetable — real slot-level data (SDD: venue-timetable-db).
 * READ-only v1: grid, slot states, class details, summary — all from
 * time_slots/class_sessions. Booking arrives with Slice B (affordances
 * deliberately absent, honest hint instead).
 *
 * Render-only (Slice A house pattern): week nav is the shared client-side
 * WeekNavigator over the all-weeks event map; venue switching happens via
 * `?venue=` full-render links (mount reads the param once — deep links work,
 * scripts re-run naturally on navigation).
 */
class VenueTimetable extends Component
{
    use ResolvesTimetableTimeline;

    public string $venueCode = '';

    #[Title('Venue Timetable')]
    public function mount(): void
    {
        $requested = (string) request()->query('venue', '');
        $venue = Venue::where('room_code', $requested)->first()
            ?? Venue::orderBy('room_code')->first();

        $this->venueCode = $venue !== null ? $venue->room_code : '';
    }

    public function render(): View
    {
        $venue = Venue::where('room_code', $this->venueCode)->first();

        if ($venue === null) {
            // Empty venues table (fresh DB) — render with empty payloads.
            return $this->renderPayload(
                collect(),
                ['code' => '', 'name' => '', 'capacity' => 0],
                array_fill(0, $this->timelineWeeks(), []),
                array_fill(0, $this->timelineWeeks(), ['total' => 0, 'available' => 0, 'occupied' => 0, 'myClasses' => 0, 'myHours' => '0']),
            );
        }

        // VenueDropdown contract (ui-common.js): code/name/capacity + display type.
        $venuesJs = Venue::orderBy('room_code')->get(['id', 'room_code', 'room_name', 'room_type', 'capacity'])
            ->map(fn (Venue $v) => [
                'code' => $v->room_code,
                'name' => $v->room_name ?? $v->room_code,
                'capacity' => (int) $v->capacity,
                'type' => match ($v->room_type) {
                    'lecture_hall' => 'LectureHall',
                    'cisco_lab' => 'CiscoLab',
                    'lab' => 'Lab',
                    default => 'Tutorial',
                },
            ]);

        // All-weeks events (no week filter) — grouped into the contiguous
        // eventsByWeek map the shared grid engine consumes (index w-1).
        $slots = TimeSlot::with(['classSession.module', 'classSession.lecturer', 'classSession.venue', 'classSession.cohorts.programme'])
            ->where('venue_id', $venue->id)
            ->whereIn('status', ['occupied', 'pending'])
            ->get();

        $weeks = $this->timelineWeeks();
        $eventsByWeek = array_fill(0, $weeks, []);
        $myUserId = auth()->id();
        // Per-week MY-slot accumulation for the ownership cards (occupied only —
        // pending bookings are not teaching). myClasses counts session ROWS
        // (each class counts separately, upstream wording), myHours = slots × 0.5.
        $mySlotCount = array_fill(0, $weeks, 0);
        $mySessionIds = array_fill(0, $weeks, []);

        foreach ($slots as $slot) {
            $session = $slot->classSession;
            $w = (int) $slot->week_number;
            if ($session === null || $w < 1 || $w > $weeks) {
                continue;
            }

            $isMine = $session->lecturer_id === $myUserId;

            $event = [
                'id' => $slot->id,
                'di' => (int) $slot->day_of_week,
                'start' => $this->slotIndex($slot->start_time),
                'end' => $this->slotIndex($slot->end_time) - 1,
                'code' => $session->module->module_code,
                'name' => $session->module->module_name,
                'type' => $session->session_type,
                'venue' => $venue->room_code,
                'lecturer' => $session->lecturer->name,
                'cohort' => $this->cohortLabel($session->cohorts),
                'cohorts' => $session->cohorts->map(fn ($c) => $this->cohortCode($c))->all(),
                'studentCount' => (int) $session->cohorts->sum('student_count'),
                'status' => $this->venueRestrictionConflict($session)
                    ? 'conflict'
                    : ($slot->status === 'pending' ? 'pending' : 'normal'),
                'remarks' => '',
                'mine' => $isMine,
            ];

            if ($isMine && $slot->status === 'occupied') {
                $mySlotCount[$w - 1]++;
                $mySessionIds[$w - 1][$session->id] = true;
            }

            // Twin-merge pre-bucketing (combined lectures): same venue + day +
            // start collapses in mergeTwinEvents() after the loop. The
            // occupied/pending partial unique index makes real twins
            // impossible until Slice B — the merge is defensive contract
            // parity, unit-tested directly (see VenueTimetableTest).
            $eventsByWeek[$w - 1][$event['di'].':'.$event['start']][] = $event;
        }

        // Grid engine consumes a list — merge twins per week and strip keys.
        $eventsByWeek = array_map(fn (array $weekMap): array => $this->mergeTwinEvents($weekMap), $eventsByWeek);

        // Per-week slot totals (venue-event-blocks-db design §3):
        // Available = free slots EXCLUDING Sunday and public-holiday slots —
        // our own legend tip says those can't be booked and the grid renders
        // them as offday cells, so counting them Available was a semantics
        // bug (holiday rows do keep status='available' in the DB).
        // sumPending is GONE (no v1 pending data); ownership cards replace it.
        $offdayByWeek = [];
        $semester = $this->timelineSemester();
        if ($semester !== null) {
            $semester->holidays()->get(['week_number', 'day_of_week'])->each(function ($h) use (&$offdayByWeek): void {
                $offdayByWeek[(int) $h->week_number][(int) $h->day_of_week] = true;
            });
        }
        // Sundays (day_of_week 6) — no DB rows exist (6×20 grid); excluded
        // for contract completeness with the cellRender offday rules.
        $isOffday = fn (int $w, int $day): bool => $day === 6 || isset($offdayByWeek[$w][$day]);

        $totalsByWeek = array_fill(0, $weeks, ['total' => 0, 'available' => 0, 'occupied' => 0, 'myClasses' => 0, 'myHours' => '0']);
        DB::table('time_slots')
            ->where('venue_id', $venue->id)
            ->selectRaw('week_number, day_of_week, status, count(*) as c')
            ->groupBy('week_number', 'day_of_week', 'status')
            ->get()
            ->each(function ($row) use (&$totalsByWeek, $weeks, $isOffday): void {
                $w = (int) $row->week_number;
                if ($w < 1 || $w > $weeks || ! isset($totalsByWeek[$w - 1][$row->status])) {
                    return;
                }
                $c = (int) $row->c;
                $totalsByWeek[$w - 1][$row->status] += $c;
                $totalsByWeek[$w - 1]['total'] += $c;
                if ($row->status === 'available' && $isOffday($w, (int) $row->day_of_week)) {
                    $totalsByWeek[$w - 1]['available'] -= $c;
                }
            });

        foreach ($mySlotCount as $i => $count) {
            $totalsByWeek[$i]['myClasses'] = count($mySessionIds[$i]);
            // 28 slots → '14', 27 slots → '13.5' (strip trailing .0).
            $totalsByWeek[$i]['myHours'] = rtrim(rtrim(sprintf('%.1f', $count * 0.5), '0'), '.');
        }

        return $this->renderPayload($venuesJs, [
            'code' => $venue->room_code,
            'name' => $venue->room_name ?? $venue->room_code,
            'capacity' => (int) $venue->capacity,
        ], $eventsByWeek, $totalsByWeek);
    }

    /**
     * @param  Collection<int, array{code: string, name: string, capacity: int, type: 'CiscoLab'|'Lab'|'LectureHall'|'Tutorial'}>  $venuesJs
     * @param  array{code: string, name: string, capacity: int}  $venue
     * @param  array<int, array<int, array<string, mixed>>>  $eventsByWeek
     * @param  array<int, array<string, int|string>>  $totalsByWeek
     */
    private function renderPayload(
        Collection $venuesJs,
        array $venue,
        array $eventsByWeek,
        array $totalsByWeek,
    ): View {
        return view('livewire.venue-timetable', [
            'venuesJs' => $venuesJs,
            'venue' => $venue,
            'eventsByWeek' => $eventsByWeek,
            'totalsByWeek' => $totalsByWeek,
            'weekData' => $this->weekData(),
            'semesterJs' => $this->semesterJs(),
            'holidaysJs' => $this->holidaysForJs(),
        ])
            ->extends('layouts.ui-template', ['activeNav' => 'venue-timetable', 'pageKey' => 'venueTimetable'])
            ->section('content');
    }

    /**
     * Twin-merge: keyed events (di:start → list) collapse into single blocks.
     * Status by severity (conflict > pending > replacement > normal), cohort
     * labels joined, cohort codes unioned, students summed, mine = OR.
     *
     * @param  array<string, array<int, array<string, mixed>>>  $keyed
     * @return array<int, array<string, mixed>>
     */
    private function mergeTwinEvents(array $keyed): array
    {
        return array_values(array_map(fn (array $twins): array => $this->reduceTwins($twins), $keyed));
    }

    /**
     * @param  array<int, array<string, mixed>>  $twins
     * @return array<string, mixed>
     */
    private function reduceTwins(array $twins): array
    {
        $merged = $twins[0];
        $severity = ['normal' => 0, 'replacement' => 1, 'pending' => 2, 'conflict' => 3];

        foreach (array_slice($twins, 1) as $twin) {
            if (($severity[$twin['status']] ?? 0) > ($severity[$merged['status']] ?? 0)) {
                $merged['status'] = $twin['status'];
            }
            if (str_contains((string) $merged['cohort'], (string) $twin['cohort']) === false) {
                $merged['cohort'] = trim((string) $merged['cohort'].' + '.$twin['cohort'], ' +');
            }
            foreach ($twin['cohorts'] as $cohortCode) {
                if (! in_array($cohortCode, $merged['cohorts'], true)) {
                    $merged['cohorts'][] = $cohortCode;
                }
            }
            $merged['studentCount'] += $twin['studentCount'];
            $merged['mine'] = $merged['mine'] || $twin['mine'];
        }

        return $merged;
    }

    /**
     * Multi-cohort label (trait cohortCode per cohort, joined) — mirrors baseEvent.
     *
     * @param  Collection<int, Cohort>  $cohorts
     */
    private function cohortLabel($cohorts): string
    {
        if ($cohorts->isEmpty()) {
            return '—';
        }
        if ($cohorts->count() === 1) {
            return $this->cohortCode($cohorts->first());
        }

        return $cohorts->map(fn ($c) => $this->cohortCode($c))->implode(' + ');
    }
}
