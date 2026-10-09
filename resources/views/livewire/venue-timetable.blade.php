{{-- Livewire full-page view (SDD: venue-timetable-db). Real slot-level data feeds
     the shared grid engine; READ-only v1 (booking = Slice B). Legacy mock template
     untouched for the D10 fallback. Week nav is client-side (Slice A pattern);
     venue switching navigates `?venue=` (full render, scripts re-run). --}}

@section('title', 'Venue Timetable')

<div class="lw-page">

    <style>
        /* Page styles: available-cell tint (engine paints empty cells as
           .cell-empty; the venue legend defines that as "Available") and the
           honest booking hint. Tokens only per §10.0. */
        .lw-page .timetable td.hour-cell .cell-empty,
        .lw-page .timetable td.hour-cell:has(.cell-empty) {
            background: var(--color-success-container);
        }
        .lw-page .timetable td.hour-cell .cell-empty:hover {
            background: var(--color-success);
        }
        .booking-hint-line {
            margin: 10px 0 0;
            font-size: 12px;
            color: var(--color-on-surface-variant);
            font-style: italic;
        }
        .venue-capacity {
            font-size: 12px;
            color: var(--color-on-surface-variant);
        }
    </style>

    <!-- ─── Page Header ─── -->
    @include('partials.ui-page-header', ['title' => 'Venue Timetable', 'description' => 'Inspect any venue\'s weekly occupancy — real slot states from the timetable database.'])

    @include('partials.ui-guide-block', [
        'guideTitle' => 'How to use this page',
        'guideItems' => [
            '<strong>Venue selection</strong> — pick a venue from the dropdown; the grid shows its full semester',
            '<strong>Week navigation</strong> — use arrows, the dropdown or Today button to browse weeks',
            '<strong>Slot colours</strong> — Available (green), Your Classes (blue), Others\' (grey), Pending (amber), Conflict (striped red — yours loud, others\' quiet)',
            '<strong>View details</strong> — click an occupied slot to see module, lecturer and cohorts',
        ]
    ])

    <!-- ─── Semester Progress ─── -->
    <div class="semester-progress" id="semesterProgress">
        <div class="progress-label" id="progressLabel"></div>
        <div class="progress-track"><div class="progress-fill" id="progressFill"></div></div>
    </div>

    <!-- ─── Semester Bar ─── -->
    <div class="semester-bar">
        @include('partials.ui-week-nav', ['prevOnclick' => 'prevWeek()', 'nextOnclick' => 'nextWeek()', 'selectId' => 'weekSelect', 'selectOnclick' => 'selectWeek(this.value)'])
    </div>

    <!-- ─── Week Subtitle ─── -->
    <div class="week-subtitle" id="weekSubtitle"></div>

    <!-- ─── Venue Selector ─── -->
    <div class="semester-bar">
        <div class="venue-dropdown-wrap">
            @include('partials.ui-venue-dropdown', ['selectId' => 'venueSelect'])
            <span class="venue-capacity" id="venueCapacity"></span>
        </div>
    </div>

    <!-- ─── Grid Wrapper ─── -->
    @include('partials.ui-grid-table')
        @include('partials.ui-empty-state', ['title' => 'No classes this week', 'text' => 'This venue is free all week.'])

    <!-- ─── Legend Bar ─── 7 items, post-merge design parity (merge-upstream-ui-2026-10 §2.2).
         Swatch colours match THIS grid's actual cell classes (.vt-cell-*) where they differ
         from upstream's mock-event classes; pending/conflict swatches are upstream-verbatim. -->
    @include('partials.ui-legend-bar', ['items' => [
        ['color' => 'var(--color-success-container)', 'label' => 'Available', 'tip' => "Free slot — booking arrives with the replacement workflow (Sunday, holiday and lead-time slots can't be booked)"],
        ['color' => 'var(--color-primary)', 'label' => 'Your Classes', 'tip' => 'Normal or replacement sessions assigned to you'],
        ['color' => 'var(--color-surface-variant)', 'label' => "Others' Classes", 'tip' => 'Normal or replacement sessions by other lecturers'],
        ['color' => 'var(--color-surface-variant)', 'label' => "Others' Pending", 'tip' => 'Replacement request by other lecturers, awaiting PL approval'],
        ['color' => 'var(--color-tertiary-container)', 'label' => 'Your Pending', 'tip' => 'Your replacement request, awaiting PL approval'],
        ['class' => 'event-conflict', 'label' => 'Your Conflict', 'tip' => 'Your conflicted class — striped, needs your attention'],
        ['color' => 'var(--color-error-container)', 'label' => "Others' Conflict", 'tip' => "Other lecturers' conflicted classes — plain red, no action needed from you"],
    ]])

    <!-- ─── Weekly Summary Bar ─── pinned 5-card set (merge-upstream-ui-2026-10 proposal) -->
    @include('partials.ui-summary-bar', [
        'cards' => [
            ['class' => 'card-total', 'valueId' => 'sumTotal', 'label' => 'Total Slots',
                'description' => 'All bookable <strong>30-minute slots</strong> for this venue in the selected week (6 days × 20 slots).'],
            ['class' => 'card-available', 'valueId' => 'sumAvailable', 'label' => 'Available',
                'description' => '<strong>Free time slots</strong> for this venue in the selected week.'],
            ['class' => 'card-replacement', 'valueId' => 'sumMyClasses', 'label' => 'My Teaching Classes',
                'description' => 'Sessions <strong>you teach</strong> in this venue this week — <strong>each class counts separately</strong>.'],
            ['class' => 'card-hours', 'valueId' => 'sumMyHours', 'label' => 'My Teaching Hours',
                'description' => 'Total hours of <strong>your classes</strong> in this venue this week (each slot = <strong>30 minutes</strong>).'],
            ['class' => 'card-conflict', 'valueId' => 'sumOccupied', 'label' => 'Occupied',
                'description' => 'Slots occupied by <strong>scheduled classes</strong> in the selected week.'],
        ]
    ])

    <p class="booking-hint-line">Slot booking arrives with the replacement workflow (Slice B).</p>

    <!-- ═══ Class Detail Modal ═══ -->
    @include('partials.ui-class-detail-modal')

    <script>
        /* Real-data bridge. DOMContentLoaded: mock-data.js / ui-common.js are
           parsed AFTER @yield('content') in the layout body (my-timetable pattern). */
        document.addEventListener('DOMContentLoaded', function () {
            MockData.semester = @json($semesterJs);
            MockData.holidays = @json($holidaysJs);

            window.weekData = generateWeekData();
            const weekData = window.weekData;
            const eventsData = @json($eventsByWeek);
            const totalsData = @json($totalsByWeek);

            window.currentWeek = currentWeekIndex();
            let currentWeek = window.currentWeek;
            window.currentModalEvent = null;

            const weekNav = new WeekNavigator(MockData.semester, weekData, null, 'venueTimetableWeek');
            weekNav._currentWeek = currentWeek;

            const venueInfo = @json($venue);

            /* Venue switching = ?venue= full render (scripts re-run naturally);
               the shared factory is callback-based — no forking. */
            new VenueDropdown(
                document.getElementById('venueSelectDropdown'),
                {
                    venues: @json($venuesJs),
                    initialCode: venueInfo.code || undefined,
                    onSelect: function (code) {
                        if (code && code !== venueInfo.code) {
                            window.location.assign('?venue=' + encodeURIComponent(code));
                        }
                    }
                }
            );

            const capacityEl = document.getElementById('venueCapacity');
            if (capacityEl && venueInfo.capacity) {
                capacityEl.textContent = 'Capacity: ' + venueInfo.capacity;
            }

            function openModal(event) {
                window.currentModalEvent = event;
                openClassModal({
                    event: event,
                    dayIndex: event.di,
                    days: weekData[currentWeek].days,
                    modalId: 'classModal',
                    title: 'Slot Details',
                    extraFields: [
                        { label: 'Cohort', value: event.cohort || '—' },
                        { label: 'Total Students', value: event.studentCount ? String(event.studentCount) : '—' },
                        { label: 'Room Name', value: venueInfo.name || '—' },
                        { label: 'Capacity', value: venueInfo.capacity ? String(venueInfo.capacity) : '—' }
                    ]
                });
            }

            function closeModal() {
                document.getElementById('classModal').style.display = 'none';
            }

            function closeModalOutside(e) {
                closeOnOverlayClick(e, closeModal);
            }

            closeOnEsc(closeModal);

            function buildTimetable() {
                currentWeek = weekNav.currentWeek;
                buildTimetableGrid({
                    events: eventsData[currentWeek] || [],
                    days: weekData[currentWeek].days,
                    onEventClick: function (e) { openModal(e); },
                    statusClassFn: function (div, e) {
                        if (e.status === 'conflict') { div.classList.add('event-conflict'); return; } // contract parity — no conflict rows in v1
                        if (e.status === 'pending') { div.classList.add('vt-cell-pending'); return; }
                        if (e.mine) { div.classList.add('vt-cell-yours'); return; }
                        div.classList.add('vt-cell-others');
                    },
                    tooltipExtra: function (e) {
                        if (e.cohorts && e.cohorts.length) return e.cohorts.join(' + ');
                        return e.cohort || '—';
                    }
                });
                updateSummary();
            }

            function updateSummary() {
                const t = totalsData[currentWeek] || { total: 0, available: 0, occupied: 0, myClasses: 0, myHours: '0' };
                const set = function (id, v) {
                    const el = document.getElementById(id);
                    if (el) el.textContent = String(v);
                };
                set('sumTotal', t.total);
                set('sumAvailable', t.available);
                set('sumMyClasses', t.myClasses);
                set('sumMyHours', t.myHours);
                set('sumOccupied', t.occupied);
                updateWeekArrows(currentWeek <= 0, currentWeek >= weekData.length - 1);
            }

            function saveWeek() { weekNav.save(); window.currentWeek = currentWeek; }

            function prevWeek() {
                weekNav.prevWeek();
                currentWeek = weekNav.currentWeek;
                window.currentWeek = currentWeek;
                saveWeek();
            }

            function nextWeek() {
                weekNav.nextWeek();
                currentWeek = weekNav.currentWeek;
                window.currentWeek = currentWeek;
                saveWeek();
            }

            function selectWeek(index) {
                weekNav.selectWeek(parseInt(index, 10));
                currentWeek = weekNav.currentWeek;
                window.currentWeek = currentWeek;
                saveWeek();
            }

            weekNav.load();
            currentWeek = weekNav.currentWeek;
            document.getElementById('semesterChip').textContent = MockData.semester.chipText;
            populateWeekSelect('weekSelect', { ranges: false, selected: currentWeek });

            buildTimetable();
            updateWeekSubtitle();
            updateProgress();

            weekNav.initTodayBtn();
            initWeekKeyboardShortcuts();

            initTimetableKeyboardHandlers({
                prevWeek: prevWeek,
                nextWeek: nextWeek,
                openModal: openModal,
                modalId: 'classModal'
            });

            initGridSwipeGestures(prevWeek, nextWeek);

            /* onclick= handlers in partials resolve via window */
            window.prevWeek = prevWeek;
            window.nextWeek = nextWeek;
            window.selectWeek = selectWeek;
            window.openModal = openModal;
            window.buildTimetable = buildTimetable;
            window.updateSummary = updateSummary;
            window.closeModal = closeModal;
            window.closeModalOutside = closeModalOutside;
        });
    </script>
</div>
