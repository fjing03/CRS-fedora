{{-- Livewire full-page view (SDD: venue-timetable-db; rewritten to event blocks
     by venue-event-blocks-db). Real slot-level data feeds the shared grid
     engine in cellRender mode — the frozen two-axis block language (colour =
     status, border = ownership). READ-only v1 (booking = Slice B). Legacy mock
     template untouched for the D10 fallback. Week nav is client-side (Slice A
     pattern); venue switching navigates `?venue=` (full render, scripts re-run). --}}

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
            '<strong>Slot colours</strong> — colour = status (green = class, amber = pending, red = conflict / public holiday), border = ownership (thick = yours, hairline = others\'); your own classes on holidays show loud red — they won\'t run',
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

    <!-- ─── Legend Bar ─── 4 items + ownership hint (venue-event-blocks-db design §2,
         frozen upstream design at f8b35a2). Available tip stays honest for the
         read-only page (booking = Slice B); swatches carry real block classes. -->
    @include('partials.ui-legend-bar', ['ownershipHint' => true, 'items' => [
        ['color' => 'var(--color-success-container)', 'label' => 'Available', 'tip' => "Free slot — booking arrives with the replacement workflow (Sunday, holiday and lead-time slots can't be booked)"],
        ['class' => 'event-normal', 'label' => 'Normal', 'tip' => 'Scheduled class with no issues (replacement sessions fold in here on this page)'],
        ['class' => 'event-pending', 'label' => 'Pending', 'tip' => 'Replacement request awaiting PL approval'],
        ['class' => 'event-conflict', 'label' => 'Conflict / Public Holiday', 'tip' => "This class will not run as scheduled — scheduling conflict or public holiday (remaining holiday slots show as empty 'PH' cells)"],
    ]])

    <!-- ─── Weekly Summary Bar ─── pinned 5-card set (Occupied kept: documented
         deviation, venue-event-blocks-db design §3); keyword spans per upstream -->
    @include('partials.ui-summary-bar', [
        'cards' => [
            ['class' => 'card-total', 'valueId' => 'sumTotal', 'label' => 'Total Slots',
                'description' => 'All time slots shown for <span class="info-keyword">this venue</span> in the selected week.'],
            ['class' => 'card-available', 'valueId' => 'sumAvailable', 'label' => 'Available',
                'description' => '<span class="info-keyword">Free time slots</span> that can be booked for this venue — Sundays and public holidays excluded.'],
            ['class' => 'card-replacement', 'valueId' => 'sumMyClasses', 'label' => 'My Teaching Classes',
                'description' => 'Sessions <span class="info-keyword">you teach</span> in this venue this week — <strong>each class counts separately</strong>.'],
            ['class' => 'card-hours', 'valueId' => 'sumMyHours', 'label' => 'My Teaching Hours',
                'description' => 'Total hours of <span class="info-keyword">your classes</span> in this venue this week (each slot = <strong>30 minutes</strong>).'],
            ['class' => 'card-conflict', 'valueId' => 'sumOccupied', 'label' => 'Occupied',
                'description' => 'Slots occupied by <span class="info-keyword">scheduled classes</span> in the selected week.'],
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
                    /* Page paints every cell (venue-event-blocks-db design §1):
                       cellRender mode = the frozen two-axis block language.
                       PH/Sunday empties get offday cells; free slots stay
                       honest green (booking = Slice B); blocks carry
                       colour=status + border=ownership. */
                    cellRender: function (td, di, hi, day, info) {
                        /* PH-day exception (holiday ONLY — Sundays hide all
                           events): the viewer's OWN classes still render as
                           loud red blocks on public-holiday days ("your class
                           won't run"); everyone else's stay empty 'PH' cells. */
                        const offMine = info && info.event && day.holiday && info.event.mine;
                        if ((day.sunday || day.holiday) && !offMine) {
                            const div = document.createElement('div');
                            div.className = 'cell-content ' + (day.holiday ? 'cell-ph' : 'cell-sun');
                            td.appendChild(div);
                        } else if (info && info.event) {
                            const e = info.event;
                            const div = document.createElement('div');
                            div.className = 'event-block span-' + info.span;   // span = e.end - e.start + 1 (half-hours)
                            div.setAttribute('tabindex', '0');
                            div.setAttribute('role', 'button');
                            div.setAttribute('aria-label', 'View class details: ' + e.code + ' ' + hours[hi]);
                            div._evt = e; div._di = di; div.__eventData = e;   // parity with default builder (keyboard handlers)
                            div.dataset.name = e.name || '';
                            div.dataset.venue = e.venue || '';
                            /* `.event-block::after` renders "name · lecturer · status"
                               (data-name + this data-tip2 — uniform format). */
                            div.dataset.tip2 = (e.lecturer || '—') + ' · ' + eventStatusLabel(e, day.holiday);
                            if (day.holiday) {
                                /* own class on a public holiday — loud red, it won't run */
                                div.classList.add('event-conflict');
                            } else if (e.status === 'pending') {
                                div.classList.add(e.mine ? 'event-mine-pending' : 'event-others-pending');
                            } else if (e.status === 'conflict') {
                                // owner-gated: mine loud 3px red; others' hairline red (§10.0 two-axis)
                                div.classList.add(e.mine ? 'event-conflict' : 'event-public-holiday');
                            } else {
                                div.classList.add(e.mine ? 'event-mine' : 'event-others');
                            }
                            const startTime = to12h(hours[e.start]);
                            const endTime = to12h(hours[e.end + 1] || add30min(hours[e.end]));
                            div.innerHTML =
                                '<span class="ev-code">' + e.code + '(' + e.type + ')</span>' +
                                '<span class="ev-venue">' + e.venue + '</span>' +
                                '<span class="ev-time">' + startTime + ' - ' + endTime + '</span>';
                            div.addEventListener('click', function () { openModal(e); });
                            td.appendChild(div);
                            if (info.span > 1) td.colSpan = info.span;   // parity: builder sets it only when span > 1
                        } else if (info && info.occupied) {
                            td.style.display = 'none';                   // continuation consumed by head's colSpan
                        } else {
                            /* Free slot — honest green (booking arrives with Slice B) */
                            const div = document.createElement('div');
                            div.className = 'cell-empty';
                            td.appendChild(div);
                        }
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
