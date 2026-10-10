{{-- Livewire full-page view (SDD: wire-backend-into-refactored-ui, task 2.3).
     Replacement Arrangement — interactive: venue dropdown (default original,
     FR 2.5) + week select drive whole-render grid re-derivation (NFR 1.1);
     grid cells are the underlying 30-minute time_slots rows (2h = 4 cells,
     addendum 2b) deriving the §10.0 table-B states with design-D4 precedence;
     click a green cell → contiguous duration block → confirm modal →
     SubmitReplacementRequest (OCC, D5). Legacy mock template untouched for
     the D10 fallback. --}}

@section('title', 'Replacement Arrangement')

<div class="lw-page">

    <style>
        /* ───── Grid (page-scoped; cell-state classes are shared theme.css) ───── */
        .arrangement-grid td.hour-cell { height: 26px; padding: 0; }
        .arrangement-grid .time-gutter {
            width: 90px; font-size: 11px; color: var(--color-on-surface-variant);
            text-align: right; padding-right: 8px; background: var(--color-surface);
        }
        .arrangement-grid th { font-size: 12px; padding: 6px 4px; }
        .arrangement-grid th .day-date { display: block; font-weight: 400; font-size: 10px; color: var(--color-on-surface-variant); }
        .arrangement-grid th .today-tag { color: var(--color-primary); font-weight: 700; }

        /* D4 rule 4 — Your Current Selection (blue, --color-primary token) */
        .cell-selected {
            position: absolute; inset: 0;
            background: var(--color-primary);
            color: var(--color-on-primary);
            font-size: 12px; font-weight: 600;
            display: flex; align-items: center; justify-content: center;
            border-radius: var(--radius-xs);
        }

        /* ───── Detail card + controls ───── */
        .session-card {
            display: flex; flex-wrap: wrap; gap: 8px 24px;
            background: var(--color-surface-variant);
            border: 1px solid var(--color-outline);
            border-radius: var(--radius-md);
            padding: 12px 16px; margin: 12px 0;
            font-size: 13px;
        }
        .session-card .detail-label { color: var(--color-on-surface-variant); font-size: 11px; display: block; }
        .session-card .detail-value { font-weight: 600; color: var(--color-on-surface); }
        .controls-row { display: flex; gap: 16px; align-items: center; margin: 12px 0; flex-wrap: wrap; }
        .controls-row label { font-size: 12px; color: var(--color-on-surface-variant); margin-right: 6px; }
        .controls-row select {
            padding: 8px 12px; border-radius: var(--radius-sm);
            border: 1px solid var(--color-outline);
            background: var(--color-surface); color: var(--color-on-surface);
            font-family: inherit; font-size: 13px;
        }

        /* ───── Selection panel ───── */
        .selection-panel {
            display: flex; align-items: center; justify-content: space-between; gap: 12px;
            border: 1px solid var(--color-primary);
            background: var(--color-primary-container);
            color: var(--color-on-primary-container);
            border-radius: var(--radius-md);
            padding: 10px 16px; margin: 12px 0;
        }
        .selection-panel .sel-title { font-weight: 700; font-size: 13px; }

        /* ───── Legend (table-B states, cell classes as swatches) ───── */
        .arr-legend { display: flex; flex-wrap: wrap; gap: 10px 18px; margin: 12px 0; font-size: 12px; align-items: center; }
        .arr-legend .swatch { display: inline-block; width: 26px; height: 14px; border-radius: var(--radius-xs); vertical-align: middle; margin-right: 5px; border: 1px solid var(--color-outline); }
    </style>

    <!-- ─── Page Header ─── -->
    @include('partials.ui-page-header', ['title' => 'Replacement Arrangement', 'description' => 'Pick a replacement slot for your class. The grid shows free half-hour cells for the selected week and venue.'])

    @include('partials.ui-guide-block', [
        'guideTitle' => 'How to use this page',
        'guideItems' => [
            '<strong>Venue & week</strong> — the venue defaults to your class\'s original room (FR 2.5); the grid recalculates instantly',
            '<strong>Green cells</strong> — free half-hour slots that fit your class duration; click to select the block',
            '<strong>Yellow / grey / red</strong> — your pending, reserved by others, occupied',
            '<strong>Submit</strong> — confirm the selection; the PL reviews it (you are notified of the outcome)',
        ]
    ])

    @if ($session === null)
        {{-- Honest no-class state (FR 4.4 spirit) — no fabricated rows. --}}
        @include('partials.ui-empty-state', [
            'visible' => true,
            'title' => 'No class selected.',
            'text' => 'Open this page from Replacement Arrangement home — pick one of your classes and click Arrange Replacement.',
        ])
    @else
        <!-- ─── Original Class Detail (FR 2.4 → 2.5 prefill) ─── -->
        <div class="session-card">
            <div>
                <span class="detail-label">Course</span>
                <span class="detail-value">{{ $session->module->module_code }} · {{ $session->module->module_name }} ({{ $session->session_type }})</span>
            </div>
            <div>
                <span class="detail-label">Original Slot</span>
                <span class="detail-value">{{ ['Mon','Tue','Wed','Thu','Fri','Sat'][$session->day_of_week] ?? '' }} {{ date('g:i A', strtotime($session->start_time)) }} – {{ date('g:i A', strtotime($session->end_time)) }}</span>
            </div>
            <div>
                <span class="detail-label">Original Venue</span>
                <span class="detail-value">{{ $session->venue->room_code }} ({{ $session->venue->room_name }})</span>
            </div>
            <div>
                <span class="detail-label">Cohort(s)</span>
                <span class="detail-value">{{ $session->cohorts->map(fn ($c) => $c->programme->programme_code.$c->current_year.'(S'.$c->semester.')G'.$c->tutorial_group)->implode(' + ') }}</span>
            </div>
            <div>
                <span class="detail-label">Duration</span>
                <span class="detail-value">{{ $grid['durationCells'] * 30 }} min ({{ $grid['durationCells'] }} half-hour cells)</span>
            </div>
        </div>

        <!-- ─── Venue + Week controls (FR 2.5 / 2.6) ─── -->
        <div class="controls-row">
            <div>
                <label for="venueSelect">Venue</label>
                <select id="venueSelect" wire:model.live.debounce.250ms="venueId">
                    @foreach ($venues as $v)
                        <option value="{{ $v->id }}" wire:key="v-{{ $v->id }}" @selected($v->id === $venueId)>
                            {{ $v->room_code }} — {{ $v->room_name }} ({{ $v->capacity }} pax)
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="weekSelect">Week</label>
                <select id="weekSelect" wire:model.live="week">
                    @foreach (range(max(1, $currentWeek + 1), $weekData ? count($weekData) : 14) as $w)
                        <option value="{{ $w }}" wire:key="w-{{ $w }}" @selected($w === $week)>Week {{ $w }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        @if ($grid !== null && count($grid['cells']) > 0)
            <!-- ─── Grid: 30-min cells × 6 days (addendum 2b axis) ─── -->
            <div class="grid-wrapper">
                <div class="grid-scroll">
                    <table class="timetable arrangement-grid" id="arrangementGrid">
                        <thead>
                            <tr>
                                <th class="time-gutter"></th>
                                @foreach (range(0, 5) as $d)
                                    @php
                                        $day = $weekData[$week - 1]['days'][$d];
                                        $isHolidayDay = $grid['cells'][$d] !== [] && collect($grid['cells'][$d])->first(fn ($c) => $c['class'] === 'cell-ph') !== null;
                                    @endphp
                                    <th wire:key="th-{{ $week }}-{{ $d }}">
                                        {{ $day['abbr'] }}{{ $day['today'] ? ' ·' : '' }}
                                        <span class="day-date {{ $day['today'] ? 'today-tag' : '' }}">{{ $day['date'] }}{{ $isHolidayDay ? ' · PH' : '' }}</span>
                                    </th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @for ($i = 0; $i < $grid['rows']; $i++)
                                <tr wire:key="gr-{{ $week }}-{{ $venueId }}-{{ $i }}">
                                    <td class="time-gutter">{{ $i % 2 === 0 ? $grid['timeLabels'][$i] : '' }}</td>
                                    @foreach (range(0, 5) as $d)
                                        @php
                                            $cell = $grid['cells'][$d][$i] ?? null;
                                            $isSelected = $cell !== null && in_array($cell['slotId'], $selectedIds, true);
                                            $class = $cell === null ? 'cell-empty' : ($isSelected ? 'cell-selected' : $cell['class']);
                                        @endphp
                                        <td class="hour-cell" wire:key="c-{{ $week }}-{{ $venueId }}-{{ $d }}-{{ $i }}">
                                            @if ($cell !== null)
                                                <div
                                                    class="cell-content {{ $class }}"
                                                    @if ($cell['clickable'] && ! $isSelected) wire:click="selectSlot({{ $cell['slotId'] }})" @endif
                                                    style="--hover-label: '{{ $isSelected ? 'Selected' : $cell['hover'] }}'"
                                                    title="{{ $cell['title'] }}"
                                                ></div>
                                            @else
                                                <div class="cell-content cell-empty"></div>
                                            @endif
                                        </td>
                                    @endforeach
                                </tr>
                            @endfor
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- ─── Legend (FR 4.11 table-B states) ─── -->
            <div class="arr-legend">
                <span><span class="swatch" style="background: var(--color-success-container)"></span>Available</span>
                <span><span class="swatch" style="background: var(--color-primary)"></span>Your Current Selection</span>
                <span><span class="swatch" style="background: var(--color-tertiary-container)"></span>Pending (You)</span>
                <span><span class="swatch" style="background: var(--color-surface-variant)"></span>Reserved by Others</span>
                <span><span class="swatch" style="background: var(--color-error-container)"></span>Occupied / Class on Public Holiday</span>
            </div>

            @if (count($grid['bookableIds']) === 0)
                {{-- FR 4.4 / FR 4.6 — even a fully occupied day stays honest --}}
                @include('partials.ui-empty-state', [
                    'visible' => true,
                    'title' => 'No available slots',
                    'text' => 'No '.$grid['durationCells'].'-cell block is free for you and the cohort(s) at this venue in week '.$week.'. Try another venue or week.',
                ])
            @endif

            <!-- ─── Your Current Selection panel ─── -->
            @if ($selection !== null)
                <div class="selection-panel">
                    <div>
                        <span class="sel-title">Your Current Selection</span><br>
                        <span style="font-size: 12px">
                            {{ ['Mon','Tue','Wed','Thu','Fri','Sat'][$selection['day']] }} · {{ $selection['start'] }} – {{ $selection['end'] }}
                            · Week {{ $week }} · {{ $venues->firstWhere('id', $selection['venue'])->room_code ?? '' }}
                            ({{ $selection['cells'] }} half-hour cells)
                        </span>
                    </div>
                    <div style="display:flex; gap:8px">
                        <button class="btn-action" type="button" wire:click="$set('selectedAnchor', null)">Clear</button>
                        <button class="btn-action" type="button" wire:click="openSubmit()">Submit Request</button>
                    </div>
                </div>
            @endif
        @endif

        <!-- ─── Submit Confirm Modal (§10.0 rule 9: confirm critical actions) ─── -->
        @if ($showSubmit)
            <div class="modal-overlay" style="display:flex" wire:key="submit-modal">
                <div class="modal" style="max-width:480px">
                    <div class="modal-header">
                        <span class="modal-title">Submit Replacement Request</span>
                        <button class="modal-close" type="button" wire:click="cancelSubmit" data-tip="Close">&times;</button>
                    </div>
                    <div class="modal-body">
                        <p style="font-size:13px; margin:0 0 12px">
                            Book
                            <strong>{{ $selection['start'] }} – {{ $selection['end'] }}</strong> on
                            <strong>{{ ['Mon','Tue','Wed','Thu','Fri','Sat'][$selection['day']] }}</strong>,
                            week <strong>{{ $week }}</strong>, venue
                            <strong>{{ $venues->firstWhere('id', $selection['venue'])->room_code ?? '' }}</strong>
                            as the replacement for <strong>{{ $session->module->module_code }}</strong>?
                            The request goes to the Programme Leader for approval.
                        </p>
                        <label style="font-size:12px; display:block; margin-bottom:4px">Remarks (optional)</label>
                        <textarea wire:model="remarks" rows="3" style="width:100%; border:1px solid var(--color-outline); border-radius:var(--radius-sm); padding:8px; font-family:inherit; font-size:13px; background:var(--color-surface); color:var(--color-on-surface)"
                            placeholder="e.g. attending official event"></textarea>
                    </div>
                    <div class="modal-footer">
                        <button class="btn-close-modal" type="button" wire:click="cancelSubmit">Cancel</button>
                        <button class="btn-action" type="button" wire:click="submit" wire:loading.attr="disabled">
                            Confirm Submission
                        </button>
                    </div>
                </div>
            </div>
        @endif
    @endif

    <!-- ─── Toast ─── -->
    <div class="copy-toast" id="arrToast"></div>

    <script>
        /* Toast for success / FR 4.10 conflict alerts (dispatch('toast')). */
        document.addEventListener('DOMContentLoaded', function () {
            let toastTimer = null;
            window.showArrToast = function (message, isError) {
                const el = document.getElementById('arrToast');
                if (!el) return;
                el.textContent = message;
                el.style.background = isError ? 'var(--color-error)' : 'var(--color-primary)';
                el.style.color = isError ? 'var(--color-on-error)' : 'var(--color-on-primary)';
                el.classList.add('show');
                clearTimeout(toastTimer);
                toastTimer = setTimeout(function () { el.classList.remove('show'); }, 3500);
            };
            Livewire.on('toast', function (payload) {
                const data = Array.isArray(payload) ? payload[0] : payload;
                const message = data && data.message ? data.message : String(data ?? '');
                const isError = /conflict|taken|no longer|cannot|not free|Pick a/i.test(message);
                window.showArrToast(message, isError);
            });
        });
    </script>
</div>
