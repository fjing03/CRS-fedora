{{-- Livewire full-page view (SDD: wire-backend-into-refactored-ui, task 2.2).
     Replacement Home — the lecturer's own classes, conflicted included, with
     start-a-replacement from details (FR 2.4) + access to request history
     (FR 2.15) and venue timetables (FR 2.14). Real data server-rendered; page
     CSS/JS inline per D2; class-detail modal + goToReplacement contract shared
     with my-timetable. Legacy mock template untouched for the D10 fallback.
     Documented divergences from the mock: sort/pagination and the days-left
     urgency column are mock-era (rows are 14-week class templates, not dated
     occurrences) — server-ordered by day/time, search kept (FR-faithful). --}}

@section('title', 'Replacement Arrangement')

<div class="lw-page">

    <style>
        /* ───── Column widths (page-scoped, from the mock template) ───── */
        .col-no { width: 40px; }
        .col-original { width: 210px; white-space: normal; }
        .col-venue { width: 80px; }
        .col-students { width: 80px; }
        .col-cohort { width: 140px; }
        .col-reason { width: 220px; vertical-align: middle; }
        .col-action { width: 190px; }
        .grid-scroll .timetable { min-width: 1120px; }

        /* ───── Responsive card view ───── */
        .replacement-card {
            background: var(--color-surface);
            border: 1px solid var(--color-outline);
            border-radius: var(--radius-md);
            padding: 14px 16px;
            margin-bottom: 8px;
            cursor: pointer;
            transition: background 0.15s, box-shadow 0.15s;
        }
        .replacement-card:hover {
            background: var(--color-surface-variant);
            box-shadow: var(--shadow-sm);
        }
        .replacement-card:active { transform: scale(0.99); }
        .rc-header {
            display: flex; align-items: center; justify-content: space-between;
            margin-bottom: 6px;
        }
        .rc-code { font-size: 14px; font-weight: 700; color: var(--color-on-surface); }
        .rc-body {
            font-size: 12px; color: var(--color-on-surface-variant); line-height: 1.6;
        }
        .rc-body strong { color: var(--color-on-surface); font-weight: 600; }
        .rc-footer {
            display: flex; align-items: center; justify-content: space-between;
            margin-top: 8px; padding-top: 8px;
            border-top: 1px solid var(--color-outline);
            font-size: 11px; color: var(--color-on-surface-variant);
        }
        .rc-footer .badge { font-size: 11px; padding: 3px 8px; }

        @media (max-width: 768px) {
            .grid-wrapper { display: none !important; }
            .card-view { display: block; }
        }
    </style>

    <!-- ─── Page Header ─── -->
    @include('partials.ui-page-header', ['title' => 'Replacement Arrangement', 'description' => 'Your classes this semester — select a class to submit a replacement request. Conflicted and cancelled classes are flagged.'])

    @include('partials.ui-guide-block', [
        'guideTitle' => 'How to use this page',
        'guideItems' => [
            '<strong>Search</strong> — filter your classes by course code or name',
            '<strong>Start replacement</strong> — click a row for details, then Replace Now',
            '<strong>Flags</strong> — Venue restriction (B005) or cancelled weeks need a replacement arrangement',
            '<strong>Track requests</strong> — My Request History shows every submission and outcome',
        ]
    ])

    <!-- ─── Toolbar: search + FR 2.14/2.15 access ─── -->
    <div class="toolbar">
        <div class="toolbar-left">
            <div class="search-wrapper">
                <svg class="search-icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <input class="search-input" type="search" wire:model.live.debounce.350ms="search" placeholder="Search by course code or name...">
            </div>
        </div>
        <div class="toolbar-right">
            <a class="btn-action" href="/my-request-history-ui" wire:navigate>
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                My Request History
            </a>
            <a class="btn-action" href="/venue-timetable-ui" wire:navigate>
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                Venue Timetable
            </a>
        </div>
    </div>

    <!-- ─── Class Table (desktop) ─── -->
    <div class="grid-wrapper">
        <div class="grid-scroll">
            <table class="timetable data-table">
                <thead>
                    <tr>
                        <th class="col-no">#</th>
                        <th>Course Code &amp; Name</th>
                        <th class="col-original">Day &amp; Time</th>
                        <th class="col-venue">Venue</th>
                        <th class="col-students">Students</th>
                        <th class="col-cohort">Cohort(s)</th>
                        <th class="col-reason">Status</th>
                        <th class="col-action">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $row)
                        <tr wire:key="row-{{ $row['id'] }}" onclick="openRow({{ $row['id'] }})" style="cursor:pointer">
                            <td class="col-no">{{ $loop->iteration }}</td>
                            <td>
                                <span class="cell-code">{{ $row['code'] }}</span>
                                <span class="cell-name">{{ $row['name'] }} <span class="cell-type-label">({{ $row['type'] }})</span></span>
                            </td>
                            <td class="col-original">
                                <div class="cell-class-block">
                                    <span class="class-day-date">{{ $row['dayLabel'] }}</span><br>
                                    <span class="class-time">{{ $row['timeLabel'] }}</span>
                                    <span class="class-duration">({{ $row['duration'] }} hr{{ $row['duration'] > 1 ? 's' : '' }})</span>
                                </div>
                            </td>
                            <td class="col-venue">{{ $row['venue'] }}</td>
                            <td class="col-students">{{ $row['studentCount'] }}</td>
                            <td class="col-cohort">{!! implode('<br>', $row['cohorts']) !!}</td>
                            <td class="col-reason">
                                @if ($row['conflicted'])
                                    <span class="badge badge-conflict">Venue restriction</span>
                                @endif
                                @if ($row['cancelledWeeks'] > 0)
                                    <span class="badge badge-conflict">{{ $row['cancelledWeeks'] }} cancelled wk{{ $row['cancelledWeeks'] > 1 ? 's' : '' }}</span>
                                @endif
                                @if ($row['activeRequests'] > 0)
                                    <span class="badge badge-pending">{{ $row['activeRequests'] }} active request{{ $row['activeRequests'] > 1 ? 's' : '' }}</span>
                                @endif
                                @if (! $row['conflicted'] && $row['cancelledWeeks'] === 0 && $row['activeRequests'] === 0)
                                    <span class="badge badge-normal">Normal</span>
                                @endif
                            </td>
                            <td class="col-action">
                                <button class="btn-action" type="button" onclick="event.stopPropagation(); replaceNow({{ $row['id'] }})">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>
                                    Arrange Replacement
                                </button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <!-- ─── Class Cards (mobile) ─── -->
    <div class="card-view">
        @foreach ($rows as $row)
            <div class="replacement-card" wire:key="card-{{ $row['id'] }}" onclick="openRow({{ $row['id'] }})">
                <div class="rc-header">
                    <span class="rc-code">{{ $row['code'] }} <span class="cell-type-label">({{ $row['type'] === 'L' ? 'Lecture' : 'Tutorial' }})</span></span>
                    @if ($row['conflicted'])
                        <span class="badge badge-conflict">Venue restriction</span>
                    @elseif ($row['cancelledWeeks'] > 0)
                        <span class="badge badge-conflict">{{ $row['cancelledWeeks'] }} cancelled</span>
                    @else
                        <span class="badge badge-normal">Normal</span>
                    @endif
                </div>
                <div class="rc-body">
                    <strong>{{ $row['name'] }}</strong><br>
                    {{ $row['dayLabel'] }} · {{ $row['timeLabel'] }} · {{ $row['venue'] }}
                </div>
                <div class="rc-footer">
                    <span>{{ implode(', ', $row['cohorts']) }}</span>
                    <button class="btn-action" type="button" onclick="event.stopPropagation(); replaceNow({{ $row['id'] }})">Arrange</button>
                </div>
            </div>
        @endforeach
    </div>

    <!-- ─── Empty State ─── -->
    @include('partials.ui-empty-state', [
        'visible' => count($rows) === 0,
        'title' => $search !== '' ? 'No classes match your search.' : 'No classes this semester.',
        'text' => $search !== '' ? 'Try a different course code or name.' : 'Your timetable has no scheduled classes to arrange replacements for.',
    ])

    <!-- ─── My Request Summary (counts by status, own scope) ─── -->
    @include('partials.ui-summary-bar', [
        'cards' => [
            ['class' => 'card-pending', 'valueId' => 'sumPending', 'value' => $requestCounts['pending'], 'label' => 'My Pending Requests',
                'description' => 'Replacement requests of yours still <span class="warn-keyword">waiting for approval</span>.'],
            ['class' => 'card-approved', 'valueId' => 'sumApproved', 'value' => $requestCounts['approved'], 'label' => 'My Approved Requests',
                'description' => 'Requests that have been <span class="info-keyword">approved</span> and are ready to proceed.'],
            ['class' => 'card-rejected', 'valueId' => 'sumRejected', 'value' => $requestCounts['rejected'], 'label' => 'My Rejected Requests',
                'description' => 'Requests that were <span class="warn-keyword">declined</span> and need alternative arrangements.'],
            ['class' => 'card-conflict', 'valueId' => 'sumConflict', 'value' => $conflictedCount, 'label' => 'My Conflicted Classes',
                'description' => 'Your classes with a <span class="warn-keyword">venue restriction</span> that need a replacement arrangement.'],
        ]
    ])

    <!-- ═══ Class Detail Modal (footer: Replace Now — FR 2.4) ═══ -->
    @section('modal-footer')
        <div class="modal-footer-left">
            <button class="btn-close-modal" onclick="closeModal()">Close</button>
        </div>
        <div class="modal-footer-right">
            <button class="btn-replace-now" id="btnReplaceNow" onclick="replaceNow(window.currentModalEvent?.id)">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>
                Replace Now
            </button>
        </div>
    @endsection
    @include('partials.ui-class-detail-modal')

    <script>
        /* Real-data bridge (my-timetable pattern). DOMContentLoaded: mock-data.js /
           ui-common.js are parsed AFTER @yield('content') in the layout body. */
        document.addEventListener('DOMContentLoaded', function () {
            MockData.semester = @json($semesterJs);
            MockData.holidays = @json($holidaysJs);
            MockData.currentUser = @json(['name' => auth()->user()?->name ?? '', 'staffId' => (string) (auth()->user()?->lecturer?->staff_id ?? '')]);

            window.weekData = generateWeekData();
            const weekData = window.weekData;
            window.currentWeek = {{ (int) $currentWeek }};
            let currentWeek = window.currentWeek;
            window.currentModalEvent = null;

            const modalEvents = @json($modalEvents);

            function openRow(id) {
                const ev = modalEvents[id];
                if (!ev) return;
                window.currentModalEvent = ev;

                openClassModal({
                    event: ev,
                    dayIndex: ev.di,
                    days: weekData[currentWeek].days,
                    modalId: 'classModal',
                    title: 'Class Details',
                    extraFields: [
                        { label: 'Cohort', value: ev.cohort },
                        { label: 'Total Students', value: String(ev.studentCount || '—') }
                    ]
                });
            }

            /* Shared goToReplacement contract (ui-common.js): the arrangement page
               reads code/cohort/day/start/end/originalVenue/duration/from. */
            function replaceNow(id) {
                const ev = modalEvents[id] || window.currentModalEvent;
                if (!ev) return;
                goToReplacement(ev.code, ev.cohort, {
                    day: ev.di,
                    start: ev.start,
                    end: ev.end,
                    venue: ev.venue,
                    duration: (ev.end - ev.start + 1) / 2
                }, 'replacement-home');
            }

            function closeModal() {
                document.getElementById('classModal').style.display = 'none';
            }

            function closeModalOutside(e) {
                closeOnOverlayClick(e, closeModal);
            }

            closeOnEsc(closeModal);

            document.getElementById('semesterChip').textContent = MockData.semester.chipText;

            /* onclick= handlers in partials resolve via window */
            window.openRow = openRow;
            window.replaceNow = replaceNow;
            window.closeModal = closeModal;
            window.closeModalOutside = closeModalOutside;
        });
    </script>
</div>
