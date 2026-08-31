{{-- Livewire full-page view (SDD wire-backend-into-refactored-ui). Real data
     feeds the shared grid engine; page CSS/JS inline per design D2. Legacy
     mock template untouched for the D10 fallback. --}}

@section('title', 'My Timetable')

<div class="lw-page">

    <style>
        /* Page styles (moved from legacy page-styles section, D2) */
        .modal-footer {
            display: flex; justify-content: space-between; align-items: center;
            padding: 0 24px 20px;
        }
        .modal-footer-left { display: flex; align-items: center; }
        .modal-footer-right { display: flex; align-items: center; gap: 10px; }
        .btn-replace-now {
            padding: 10px 20px;
            border-radius: var(--radius-md);
            border: none;
            background: var(--color-error-container);
            color: var(--color-on-error-container);
            font-family: inherit;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: background var(--transition), transform 0.15s;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .btn-replace-now:hover { filter: brightness(1.1); }
        .btn-replace-now:active { transform: scale(0.97); }
        .btn-replace-now:focus-visible {
            outline: 2px solid var(--color-primary);
            outline-offset: 2px;
        }
        .timetable td.hour-cell.offday-slot { background: transparent; }
        .timetable td.today-cell.offday-slot { background: transparent; }
        .timetable td.hour-cell.offday-slot .cell-empty { background: transparent; }
    </style>

    <!-- ─── Page Header ─── -->
    @include('partials.ui-page-header', ['title' => 'My Timetable', 'description' => 'View your weekly class schedule and manage replacement requests across all cohorts.'])

    @include('partials.ui-guide-block', [
        'guideTitle' => 'How to use this page',
        'guideItems' => [
            '<strong>Week navigation</strong> — use arrows or Today button to browse weeks',
            '<strong>Slot status</strong> — Normal (green), Conflicted (red), Pending (amber), Approved (blue), Rejected (grey)',
            '<strong>Request replacement</strong> — click any conflicted slot to open the request form',
            '<strong>View details</strong> — click a normal/approved slot to see class details',
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

    <!-- ─── Grid Wrapper ─── -->
    @include('partials.ui-grid-table')
        @include('partials.ui-empty-state', ['title' => 'No classes this week', 'text' => 'All classes for this week have been cancelled.'])

    <!-- ─── Legend Bar ─── -->
    @include('partials.ui-legend-bar')

    <!-- ─── Weekly Summary Bar ─── -->
    @include('partials.ui-summary-bar', [
        'cards' => [
            ['class' => 'card-total', 'valueId' => 'sumTotal', 'label' => 'Total Classes',
                'description' => 'Total classes in <strong>your weekly timetable</strong> for the selected week.'],
            ['class' => 'card-hours', 'valueId' => 'sumHours', 'label' => 'Teaching Hours',
                'description' => 'Total <strong>teaching hours</strong> in your timetable for the selected week (each slot = <strong>30 minutes</strong>).'],
            ['class' => 'card-replacement', 'valueId' => 'sumReplacement', 'label' => 'Replacements',
                'description' => 'Classes where a <strong>replacement lecturer</strong> is covering you this week.'],
            ['class' => 'card-pending', 'valueId' => 'sumPending', 'label' => 'Pending',
                'description' => 'Replacement requests of yours still <strong>waiting for approval</strong> or a volunteer.'],
            ['class' => 'card-conflict', 'valueId' => 'sumConflict', 'label' => 'Conflicts',
                'description' => '<strong>Scheduling clashes</strong> in your timetable or classes on <strong>public holidays</strong> that need attention.'],
        ]
    ])

    <!-- ═══ Class Detail Modal (footer: Replace Now on conflicted days) ═══ -->
    @section('modal-footer')
        <div class="modal-footer-left">
            <button class="btn-close-modal" onclick="closeModal()">Close</button>
        </div>
        <div class="modal-footer-right">
            <button class="btn-replace-now" id="btnReplaceNow" style="display:none" onclick="goToReplacement(currentModalEvent?.code, currentModalEvent?.cohort, { day: currentModalEvent?.di, start: currentModalEvent?.start, end: currentModalEvent?.end, venue: currentModalEvent?.venue, duration: (currentModalEvent && currentModalEvent.start !== undefined && currentModalEvent.end !== undefined) ? ((currentModalEvent.end - currentModalEvent.start + 1) / 2) : undefined }, 'my-timetable')">Replace Now</button>
        </div>
    @endsection
    @include('partials.ui-class-detail-modal')

    <!-- ═══ Copy Toast ═══ -->
    <div class="copy-toast" id="copyToast"></div>

    <script>
        /* ───── Real-data bridge: shared engine consumes server-fed registries ───── */
        MockData.semester = @json($semesterJs);
        MockData.holidays = @json($holidaysJs);
        MockData.currentUser = @json(['name' => auth()->user()?->name ?? '', 'staffId' => (string) (auth()->user()?->lecturer?->staff_id ?? '')]);

        const weekData = generateWeekData();
        const eventsData = @json($eventsByWeek);

        let currentWeek = {{ (int) ($currentWeek ?? 0) }};
        let currentModalEvent = null;

        const weekNav = new WeekNavigator(MockData.semester, weekData);
        weekNav._currentWeek = currentWeek;

        function saveWeek() { weekNav.save(); }

        function openModal(event) {
            currentModalEvent = event;

            const days = weekData[currentWeek].days;
            const isConflict = days[event.di] && days[event.di].holiday;

            const replaceBtn = document.getElementById('btnReplaceNow');
            replaceBtn.style.display = isConflict ? 'flex' : 'none';

            let cohortValue = event.cohort;
            let studentValue = event.studentCount ? String(event.studentCount) : '—';
            if (event.cohorts && event.cohorts.length > 1 && event.studentCount) {
                studentValue = String(event.studentCount);
            }

            openClassModal({
                event: event,
                dayIndex: event.di,
                days: days,
                modalId: 'classModal',
                title: 'Class Details',
                extraFields: [
                    { label: 'Cohort', value: cohortValue },
                    { label: 'Total Students', value: studentValue }
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
                onEventClick: function(e) { openModal(e); },
                tooltipExtra: function(e) {
                    // Viewer is the lecturer — tooltip shows the cohort(s).
                    if (e.cohorts && e.cohorts.length) return e.cohorts.join(' + ');
                    return e.cohort || e.venue || '—';
                },
                replacementNoteFn: function(e) {
                    return buildReplacementNote(e);
                }
            });
            updateSummary();
        }

        function updateSummary() {
            const events = eventsData[currentWeek] || [];
            computeSummary(events, weekData[currentWeek].days);
            updateWeekArrows(currentWeek <= 0, currentWeek >= weekData.length - 1);
        }

        function prevWeek() {
            weekNav.prevWeek();
            currentWeek = weekNav.currentWeek;
            saveWeek();
        }

        function nextWeek() {
            weekNav.nextWeek();
            currentWeek = weekNav.currentWeek;
            saveWeek();
        }

        function selectWeek(index) {
            weekNav.selectWeek(parseInt(index, 10));
            currentWeek = weekNav.currentWeek;
            saveWeek();
        }

        document.addEventListener('DOMContentLoaded', function() {
            weekNav.load();
            currentWeek = weekNav.currentWeek;
            document.getElementById('semesterChip').textContent = MockData.semester.chipText;
            populateWeekSelect('weekSelect', { ranges: false, selected: currentWeek });

            buildTimetable();
            updateWeekSubtitle();
            updateProgress();
        });

        weekNav.initTodayBtn();
        initWeekKeyboardShortcuts();

        initTimetableKeyboardHandlers({
            prevWeek: prevWeek,
            nextWeek: nextWeek,
            openModal: openModal,
            modalId: 'classModal'
        });

        initEvCodeCopy('copyToast');

        initGridSwipeGestures(prevWeek, nextWeek);
    </script>
</div>
