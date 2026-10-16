<?php

namespace App\Livewire;

use App\Concerns\ResolvesTimetableTimeline;
use App\Models\ClassSession;
use App\Models\Cohort;
use App\Models\ReplacementRequest;
use App\Models\TimeSlot;
use App\Models\Venue;
use App\Services\LectureSessionCatalog;
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

    /**
     * "Select a Session" model (SDD arrange-intent-session-choice design D2/D3,
     * user directive 2026-10-16 apply round 4): the pool is WEEK-AGNOSTIC —
     * every lecturer session with ≥1 conflicted occurrence anywhere in the
     * semester that THIS venue admits (venue-entry + B005 rules). A conflicted
     * class may be arranged at any week past the lead time; $week1 is used
     * only to exclude the booked week from the conflictWeeks jump hints.
     * Wire-callable, owner-pinned (auth()->id() query — never another
     * lecturer's sessions); duration/day-end + span fit is client arithmetic
     * against the shared hours constant + the week's own grid, not part of
     * this rule.
     *
     * @param  int  $week1  1-based booked week (the clicked cell's week + 0-based→1-based convention)
     * @return array{modules: list<array<string, mixed>>, roomType: string, venue: string, conflictWeeks: list<int>}
     */
    public function intentSessionOptions(int $week1): array
    {
        /* D2 apply fix (wire-call reality): Livewire does not persist private
           render state into a subsequent wire-call request — so the venue facts
           are re-derived from the HYDRATED public $venueCode (tamper-proof:
           an unknown code resolves to no venue → empty options, never an error). */
        $venueRow = Venue::where('room_code', $this->venueCode)->first();
        if ($venueRow === null) {
            return ['modules' => [], 'roomType' => '', 'venue' => $this->venueCode, 'conflictWeeks' => []];
        }
        $rawRoomType = (string) $venueRow->room_type;
        $semesterId = $this->timelineSemester()?->id;
        $catalog = new LectureSessionCatalog;

        $lecturerSessions = ClassSession::query()
            ->with(['module', 'venue', 'cohorts.programme'])
            ->where('lecturer_id', (int) auth()->id())
            ->where('semester_id', $semesterId)
            ->orderBy('day_of_week')
            ->orderBy('start_time')
            ->orderBy('id')
            ->get();

        /* USER DIRECTIVE (2026-10-16, apply round 4): a conflicted class can be
           arranged at ANY week past the lead time — the "Jump to Week" buttons
           are a HINT (where the need lives), never a restriction. The modal
           pool is therefore WEEK-AGNOSTIC: every session with ≥1 conflicted
           occurrence anywhere in the semester that this venue's type admits
           (venue-entry + B005 cohort rule), regardless of the viewed week. */
        $conflicted = $catalog->conflictedFor($lecturerSessions);
        $eligible = $lecturerSessions
            ->filter(fn (ClassSession $s): bool => isset($conflicted[$s->id])
                && LectureSessionCatalog::sessionAllowedForVenue((string) $s->session_type, $rawRoomType, $s, (string) $venueRow->room_code))
            ->values();

        $built = array_values($catalog->build($eligible, null));
        foreach ($built as $moduleIndex => $module) {
            foreach ($module['order'] as $sessionId) {
                $facts = $conflicted[$sessionId];
                $built[$moduleIndex]['sessions'][(string) $sessionId]['conflictReason'] = $facts['reason'];
                $built[$moduleIndex]['sessions'][(string) $sessionId]['conflictWeeks'] = $facts['weeks'];
            }
        }

        /* Jump-to-week hint: weeks holding the ELIGIBLE conflicted occurrences
           (the candidate pool's own conflict weeks), minus the booked week. */
        $weekSet = [];
        foreach ($eligible as $s) {
            foreach ($conflicted[$s->id]['weeks'] as $week) {
                $weekSet[$week] = true;
            }
        }
        $conflictWeeks = array_values(array_filter(array_keys($weekSet), fn (int $w): bool => $w !== $week1));
        sort($conflictWeeks);

        return [
            'modules' => $built,
            'roomType' => $rawRoomType,
            'venue' => $venueRow->room_code,
            'conflictWeeks' => $conflictWeeks,
        ];
    }

    public function render(): View
    {
        $venue = Venue::where('room_code', $this->venueCode)->first();

        if ($venue === null) {
            // Empty venues table (fresh DB) — render with empty payloads.
            return $this->renderPayload(
                collect(),
                ['code' => '', 'name' => '', 'capacity' => 0, 'rawRoomType' => ''],
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
                // Raw DB encoding rides along (SDD arrange-intent-session-choice
                // D0/D1): the modal + fallback consume ruleMap over raw values;
                // the display mapping below stays for the dropdown filter.
                'rawRoomType' => (string) $v->room_type,
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
        // Coalescing groups (venue-block-span-coalescing): keyed w:day:session.
        $groups = [];

        foreach ($slots as $slot) {
            $session = $slot->classSession;
            $w = (int) $slot->week_number;
            if ($session === null || $w < 1 || $w > $weeks) {
                continue;
            }

            // Ownership cards stay ROW-counted (each 30-min slot = 0.5 h).
            $isMine = $session->lecturer_id === $myUserId;
            if ($isMine && $slot->status === 'occupied') {
                $mySlotCount[$w - 1]++;
                $mySessionIds[$w - 1][$session->id] = true;
            }

            $start = $this->slotIndex($slot->start_time);
            $end = $this->slotIndex($slot->end_time) - 1;
            $severity = $this->venueRestrictionConflict($session)
                ? 3
                : ($slot->status === 'pending' ? 2 : 0);

            // Coalescing pre-pass (venue-block-span-coalescing S1): the import
            // stores a full half-hour grid per venue/week — a class OCCUPIES its
            // adjacent rows (a 2-hour lecture = 4 rows). Group rows by
            // class_session_id within (week, day) and emit ONE event spanning
            // min→max slot index. NEVER key on module code + adjacency: B006
            // Monday runs BMIT2154 (L) 09:00–11:00 straight into BMIT2154 (T)
            // 11:00–12:00 — two distinct sessions. Min/max semantics (the slot
            // query has no orderBy); rows are contiguous by construction, so no
            // gap-splitting. Rendering-only: the cards below stay row-counted.
            $key = $w.':'.$slot->day_of_week.':'.$session->id;
            if (! isset($groups[$key])) {
                $groups[$key] = [
                    'w' => $w,
                    'di' => (int) $slot->day_of_week,
                    'id' => $slot->id,
                    'start' => $start,
                    'end' => $end,
                    'severity' => $severity,
                    'session' => $session,
                ];

                continue;
            }

            $g = &$groups[$key];
            $g['id'] = min($g['id'], $slot->id);
            $g['start'] = min($g['start'], $start);
            $g['end'] = max($g['end'], $end);
            $g['severity'] = max($g['severity'], $severity);
            unset($g);
        }

        foreach ($groups as $g) {
            $session = $g['session'];
            $event = [
                'id' => $g['id'],
                'di' => $g['di'],
                'start' => $g['start'],
                'end' => $g['end'],
                'code' => $session->module->module_code,
                'name' => $session->module->module_name,
                'type' => $session->session_type,
                'venue' => $venue->room_code,
                'lecturer' => $session->lecturer->name,
                'cohort' => $this->cohortLabel($session->cohorts),
                'cohorts' => $session->cohorts->map(fn ($c) => $this->cohortCode($c))->all(),
                'studentCount' => (int) $session->cohorts->sum('student_count'),
                'status' => match ($g['severity']) {
                    3 => 'conflict',
                    2 => 'pending',
                    default => 'normal',
                },
                'remarks' => '',
                'mine' => $session->lecturer_id === $myUserId,
            ];

            // Twin-merge pre-bucketing (combined lectures): same venue + day +
            // start collapses in mergeTwinEvents() after coalescing. The
            // occupied/pending partial unique index makes real twins
            // impossible until Slice B — the merge is defensive contract
            // parity, unit-tested directly (see VenueTimetableTest).
            $eventsByWeek[$g['w'] - 1][$event['di'].':'.$event['start']][] = $event;
        }

        // Reserved-slot second pass (venue-pending-and-type-labels D1): the
        // $slots query already fetched this venue's pending rows, but the
        // imported-class path above skips them (no class_session_id to
        // coalesce). Each active request reserves exactly ONE anchor
        // half-hour slot (spec Amendment 2b) — emit one event per slot, no
        // coalescing; mergeTwinEvents below still runs over them for
        // defensive parity with imported events. Class facts come from the
        // request's ORIGINAL session (the class being replaced — that is
        // what will occupy this room); no `proposer` eager load because
        // `mine` reads the proposer_id column directly.
        $reserved = $slots->where('class_session_id', null)->where('status', 'pending');
        $slotIds = $reserved->pluck('id');
        $requests = ReplacementRequest::with(['classSession.module', 'classSession.lecturer', 'classSession.cohorts.programme'])
            ->where('status', 'pending')
            ->whereIn('replacement_time_slot_id', $slotIds)
            ->get()
            ->keyBy('replacement_time_slot_id');

        foreach ($reserved as $slot) {
            $request = $requests->get($slot->id);
            $session = $request?->classSession;
            // Orphan defence (design D1): cancel/submit keep slot+request in
            // lockstep, so a pending slot without an active request (or the
            // class row it points at) should not exist — emit nothing, no
            // error, no log noise.
            if ($request === null || $session === null) {
                continue;
            }
            // Week guard: same 1..$weeks window as the imported path.
            $w = (int) $slot->week_number;
            if ($w < 1 || $w > $weeks) {
                continue;
            }

            $start = $this->slotIndex($slot->start_time);
            $end = $this->slotIndex($slot->end_time) - 1;
            $event = [
                'id' => $slot->id,
                'di' => (int) $slot->day_of_week,
                'start' => $start,
                'end' => $end,
                'code' => $session->module->module_code,
                'name' => $session->module->module_name,
                'type' => $session->session_type,
                'venue' => $venue->room_code,
                'lecturer' => $session->lecturer->name,
                'cohort' => $this->cohortLabel($session->cohorts),
                'cohorts' => $session->cohorts->map(fn ($c) => $this->cohortCode($c))->all(),
                'studentCount' => (int) $session->cohorts->sum('student_count'),
                'status' => 'pending', // amber per the frozen legend — no conflict derivation in this change
                'remarks' => 'Replacement request awaiting PL approval', // byte-matches the legend tip
                'mine' => $request->proposer_id === $myUserId,
            ];

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
            'rawRoomType' => (string) $venue->room_type, // D2: the modal's server-side eligibility key
        ], $eventsByWeek, $totalsByWeek);
    }

    /**
     * @param  Collection<int, array{code: string, name: string, capacity: int, rawRoomType: string, type: 'CiscoLab'|'Lab'|'LectureHall'|'Tutorial'}>  $venuesJs
     * @param  array{code: string, name: string, capacity: int, rawRoomType: string}  $venue
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
