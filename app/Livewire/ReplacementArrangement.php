<?php

namespace App\Livewire;

use App\Actions\SubmitReplacementRequest;
use App\Concerns\ResolvesTimetableTimeline;
use App\Http\Requests\StoreReplacementRequest;
use App\Models\ClassSession;
use App\Models\Holiday;
use App\Models\ReplacementRequest;
use App\Models\TimeSlot;
use App\Models\Venue;
use App\Services\MatrixIntersectionEngine;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Replacement Arrangement (FR 2.5–2.9, 4.3–4.11) — interactive Livewire:
 * session detail, venue dropdown (default = original venue, FR 2.5), and the
 * weekly grid on the underlying 30-minute time_slots axis (2h = 4 cells;
 * addendum 2b). Cell states derive the §10.0 table-B states with the design
 * D4 precedence; a click selects the contiguous duration block from the
 * anchor (display-only — only the anchor half-hour slot is reserved, the
 * documented prototype limitation). Submit runs SubmitReplacementRequest with
 * the D8 rules; the grid re-derives on every render (venue change ≤500 ms,
 * NFR 1.1). Slice B (SDD: wire-backend-into-refactored-ui).
 */
class ReplacementArrangement extends Component
{
    use ResolvesTimetableTimeline;

    private const GRID_START = '08:00:00';

    private const GRID_ROWS = 20; // 20 half-hour rows, 08:00–18:00 (TimeSlotsSeeder axis)

    public int $sessionId = 0;

    public int $week = 1;

    public int $venueId = 0;

    public ?int $selectedAnchor = null;

    public string $remarks = '';

    public bool $showSubmit = false;

    #[Title('Replacement Arrangement')]
    public function mount(): void
    {
        $params = request();
        $this->week = $params->filled('week')
            ? max(1, (int) $params->input('week'))
            : $this->currentTimelineWeekIndex() + 1;

        // Resolve the original session from the shared goToReplacement contract
        // (code + day + start slot index); fall back to the first own session —
        // the page must never crash on dead links (FR 4.4 spirit).
        $semester = $this->timelineSemester();
        $query = ClassSession::query()
            ->where('lecturer_id', auth()->id())
            ->where('semester_id', $semester !== null ? $semester->id : 1);

        if ($params->filled('code')) {
            $query->whereHas('module', fn ($m) => $m->where('module_code', (string) $params->input('code')));
        }
        if ($params->filled('day')) {
            $query->where('day_of_week', (int) $params->input('day'));
        }

        $sessions = $query->orderBy('day_of_week')->orderBy('start_time')->get();
        if ($params->filled('start')) {
            $sessions = $sessions->filter(
                fn (ClassSession $s): bool => $this->slotIndex($s->start_time) === (int) $params->input('start'),
            );
        }

        $hasParams = $params->filled('code') || $params->filled('day') || $params->filled('start');
        $session = $sessions->first();

        // Fallback only for bare navigation (no params) — a dead/tampered deep
        // link must land on the honest "No class selected" state, never on an
        // unrelated own session (FR 2.11 spirit).
        if ($session === null && ! $hasParams) {
            $session = ClassSession::query()
                ->where('lecturer_id', auth()->id())
                ->where('semester_id', $semester !== null ? $semester->id : 1)
                ->orderBy('day_of_week')
                ->orderBy('start_time')
                ->first();
        }

        if ($session === null) {
            // Honest dead-link state — the page renders "No class selected".
            $this->sessionId = 0;
            $this->venueId = 0;
            $this->clampWeek();

            return;
        }

        $this->sessionId = (int) $session->id;

        // FR 2.5 — venue dropdown defaults to the original class venue.
        $this->venueId = (int) $session->venue_id;
        if ($params->filled('originalVenue')) {
            $original = Venue::query()->where('room_code', (string) $params->input('originalVenue'))->first();
            if ($original !== null) {
                $this->venueId = (int) $original->id;
            }
        }

        $this->clampWeek();
    }

    public function updatedVenueId(): void
    {
        $this->selectedAnchor = null; // selection is venue-specific
    }

    public function updatedWeek(): void
    {
        $this->selectedAnchor = null; // selection is week-specific
    }

    /**
     * Select the contiguous duration block from the clicked anchor (FR 2.8).
     */
    public function selectSlot(int $slotId): void
    {
        $grid = $this->gridData();

        $run = $grid['runByAnchor'][$slotId] ?? null;
        if ($run === null) {
            $this->dispatch('toast', message: 'That slot cannot host this class.');

            return;
        }

        $index = array_search($slotId, $run, true);
        $block = array_slice($run, (int) $index, $grid['durationCells']);
        if (count($block) < $grid['durationCells']) {
            $this->dispatch('toast', message: "Not enough room after this time for a {$grid['durationCells']}-cell block — pick an earlier start.");

            return;
        }

        $this->selectedAnchor = $slotId;
        $this->showSubmit = false;
    }

    public function openSubmit(): void
    {
        if ($this->selectedAnchor === null) {
            $this->dispatch('toast', message: 'Pick a green slot first.');

            return;
        }

        $this->showSubmit = true;
    }

    public function cancelSubmit(): void
    {
        $this->showSubmit = false;
    }

    /**
     * FR 2.9 submit — D8 rules at the component boundary, then the action
     * (ownership, engine pre-check, OCC reserve, D5 compensation). Success and
     * conflict both re-derive the grid on the following render.
     */
    public function submit(SubmitReplacementRequest $action): void
    {
        $session = $this->ownSession();
        if ($session === null || $this->selectedAnchor === null) {
            $this->dispatch('toast', message: 'Pick a green slot first.');

            return;
        }

        $validator = Validator::make([
            'class_session_id' => $session->id,
            'week_number' => $this->week,
            'replacement_time_slot_id' => $this->selectedAnchor,
            'remarks' => $this->remarks,
        ], StoreReplacementRequest::rules());

        if ($validator->fails()) {
            $this->dispatch('toast', message: $validator->errors()->first());

            return;
        }

        $result = $action(auth()->user(), $session, $this->week, (int) $this->selectedAnchor, $this->remarks);

        $this->showSubmit = false;

        if ($result['ok']) {
            $this->selectedAnchor = null;
            $this->remarks = '';
            $this->dispatch('toast', message: 'Replacement request submitted — waiting for PL approval.');

            return;
        }

        // FR 4.10 — conflict alert to the loser; the grid re-derivation below
        // shows the winner's pending as Reserved by Others.
        $this->selectedAnchor = null;
        $this->dispatch('toast', message: match ($result['conflict_reason']) {
            'slot_not_available', 'slot_not_found' => 'That slot was just taken — pick another green cell.',
            'engine_precheck_failed' => 'You or the cohort is no longer free at that time.',
            default => 'Booking conflict — the grid has been refreshed.',
        });
    }

    public function render(): View
    {
        $session = $this->ownSession();

        if ($session === null) {
            return view('livewire.replacement-arrangement', [
                'session' => null,
                'grid' => null,
                'weekData' => $this->weekData(),
                'semesterJs' => $this->semesterJs(),
                'holidaysJs' => $this->holidaysForJs(),
                'venues' => [],
                'selectedIds' => [],
                'selection' => null,
                'currentWeek' => $this->currentTimelineWeekIndex(),
            ])
                ->extends('layouts.ui-template', ['activeNav' => 'replacement-arrangement', 'pageKey' => 'replacementArrangement'])
                ->section('content');
        }

        $grid = $this->gridData();

        return view('livewire.replacement-arrangement', [
            'session' => $session,
            'grid' => $grid,
            'weekData' => $this->weekData(),
            'semesterJs' => $this->semesterJs(),
            'holidaysJs' => $this->holidaysForJs(),
            'venues' => Venue::query()->orderBy('room_code')->get(['id', 'room_code', 'room_name', 'capacity']),
            'selectedIds' => $grid['selectedIds'],
            'selection' => $grid['selection'],
            'currentWeek' => $this->currentTimelineWeekIndex(),
        ])
            ->extends('layouts.ui-template', ['activeNav' => 'replacement-arrangement', 'pageKey' => 'replacementArrangement'])
            ->section('content');
    }

    /**
     * Grid payload for the selected week × venue: cells with the D4-derived
     * state, bookable slot ids, run lookup for block selection, and the
     * current selection block.
     *
     * @return array<string, mixed>
     */
    private function gridData(): array
    {
        $this->clampWeek();
        $engine = app(MatrixIntersectionEngine::class);

        $session = $this->ownSession();
        if ($session === null) {
            return ['cells' => [], 'bookableIds' => [], 'runByAnchor' => [], 'selectedIds' => [], 'selection' => null, 'runs' => []];
        }

        $semester = $this->timelineSemester();
        $semesterId = $semester !== null ? $semester->id : 1;
        $cohortIds = $session->cohorts->pluck('id')->all();
        $durationCells = (int) (Carbon::parse($session->start_time)->diffInMinutes(Carbon::parse($session->end_time)) / 30);

        $runs = $engine->findAvailableSlots(
            (int) auth()->id(),
            $cohortIds,
            $semesterId,
            $this->week,
            $session->session_type,
            $durationCells * 30,
            $this->venueId,
        );

        $bookableIds = [];
        $runBySlot = [];
        foreach ($runs as $run) {
            $ids = $run['time_slot_ids'];
            foreach ($ids as $id) {
                $bookableIds[] = $id;
                $runBySlot[$id] = $ids;
            }
        }

        $busy = $engine->busyRanges((int) auth()->id(), $cohortIds, $semesterId, $this->week);

        $slots = TimeSlot::query()
            ->where('semester_id', $semesterId)
            ->where('week_number', $this->week)
            ->where('venue_id', $this->venueId)
            ->get()
            ->keyBy(fn (TimeSlot $s): string => $s->day_of_week.'|'.$s->start_time);

        $pendingBySlot = ReplacementRequest::query()
            ->where('status', 'pending')
            ->whereIn('replacement_time_slot_id', $slots->pluck('id'))
            ->pluck('proposer_id', 'replacement_time_slot_id');

        $holidayDays = Holiday::query()
            ->where('semester_id', $semesterId)
            ->where('week_number', $this->week)
            ->pluck('label', 'day_of_week');

        $days = range(0, 5);
        $cells = [];
        foreach ($days as $day) {
            for ($i = 0; $i < self::GRID_ROWS; $i++) {
                $time = date('H:i:s', strtotime(self::GRID_START) + $i * 1800);
                $slot = $slots[$day.'|'.$time] ?? null;

                $cells[$day][$i] = $this->deriveCell(
                    $slot,
                    $busy,
                    $bookableIds,
                    $pendingBySlot,
                    $holidayDays->has($day),
                    $durationCells,
                );
            }
        }

        // Current selection block: the duration cells starting at the anchor,
        // contiguous inside the anchor's run; invalidated honestly if the
        // re-derivation broke it (grid is the single source of truth).
        $selectedIds = [];
        $selection = null;
        if ($this->selectedAnchor !== null) {
            $run = $runBySlot[$this->selectedAnchor] ?? null;
            if ($run !== null) {
                $index = array_search($this->selectedAnchor, $run, true);
                $block = array_slice($run, (int) $index, $durationCells);
                if (count($block) === $durationCells) {
                    $selectedIds = $block;
                    $anchor = $slots->firstWhere('id', $this->selectedAnchor);
                    if ($anchor !== null) {
                        $selection = [
                            'day' => $anchor->day_of_week,
                            'start' => Carbon::parse($anchor->start_time)->format('g:i A'),
                            'end' => Carbon::parse($block[count($block) - 1] === $anchor->id ? $anchor->end_time : (string) $slots->firstWhere('id', $block[count($block) - 1])->end_time)->format('g:i A'),
                            'venue' => $this->venueId,
                            'cells' => $durationCells,
                        ];
                    }
                }
            }
        }

        return [
            'cells' => $cells,
            'days' => $days,
            'rows' => self::GRID_ROWS,
            'timeLabels' => collect(range(0, self::GRID_ROWS - 1))
                ->map(fn (int $i): string => Carbon::parse(self::GRID_START)->addMinutes($i * 30)->format('g:i A'))
                ->all(),
            'bookableIds' => $bookableIds,
            'runByAnchor' => $runBySlot,
            'selectedIds' => $selectedIds,
            'selection' => $selection,
            'runs' => $runs,
            'durationCells' => $durationCells,
        ];
    }

    /**
     * D4 cell-state derivation, precedence order 1–5.
     *
     * @param  array<int, array{day: int, start: string, end: string}>  $busy
     * @param  array<int, int>  $bookableIds
     * @param  Collection<int, int>  $pendingBySlot
     * @return array{class: string, clickable: bool, slotId: int|null, hover: string, title: string}
     */
    private function deriveCell(
        ?TimeSlot $slot,
        array $busy,
        array $bookableIds,
        $pendingBySlot,
        bool $isHoliday,
        int $durationCells,
    ): array {
        if ($slot === null) {
            return ['class' => 'cell-empty', 'clickable' => false, 'slotId' => null, 'hover' => '', 'title' => ''];
        }

        if ($isHoliday) {
            // D4 rule 1 legend label — "Class on Public Holiday".
            return ['class' => 'cell-ph', 'clickable' => false, 'slotId' => $slot->id, 'hover' => 'PH', 'title' => 'Public holiday'];
        }

        $busyHit = collect($busy)->contains(
            fn (array $range): bool => $range['day'] === (int) $slot->day_of_week
                && $range['start'] < $slot->end_time
                && $range['end'] > $slot->start_time,
        );

        if ($slot->status === 'occupied' || $busyHit) {
            return ['class' => 'cell-occupied', 'clickable' => false, 'slotId' => $slot->id, 'hover' => 'Occupied', 'title' => 'Occupied by a class'];
        }

        if ($slot->status === 'pending') {
            $proposer = $pendingBySlot->get($slot->id);
            if ($proposer !== null && (int) $proposer === (int) auth()->id()) {
                return ['class' => 'cell-pending', 'clickable' => false, 'slotId' => $slot->id, 'hover' => 'Pending (You)', 'title' => 'Your pending request'];
            }

            return ['class' => 'cell-reserved', 'clickable' => false, 'slotId' => $slot->id, 'hover' => 'Reserved by Others', 'title' => 'Another lecturer holds a pending request here'];
        }

        if (in_array($slot->id, $bookableIds, true)) {
            return ['class' => 'cell-available', 'clickable' => true, 'slotId' => $slot->id, 'hover' => 'Select', 'title' => "Free for a {$durationCells}-cell block"];
        }

        // Free but the duration block cannot fit here (engine runs are
        // duration-filtered) — neutral, non-clickable, honest tooltip.
        return ['class' => 'cell-empty', 'clickable' => false, 'slotId' => $slot->id, 'hover' => '', 'title' => "Too short for this {$durationCells}-cell class"];
    }

    private function ownSession(): ?ClassSession
    {
        if ($this->sessionId === 0) {
            return null;
        }

        $session = ClassSession::query()
            ->with(['module', 'venue', 'cohorts.programme'])
            ->find($this->sessionId);

        // FR 2.11 — ownership scoping: only the owner arranges their class.
        if ($session === null || (int) $session->lecturer_id !== (int) auth()->id()) {
            return null;
        }

        return $session;
    }

    private function clampWeek(): void
    {
        $this->week = max(1, min($this->timelineWeeks(), $this->week));
    }
}
