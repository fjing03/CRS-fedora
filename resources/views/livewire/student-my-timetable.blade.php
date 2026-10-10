{{-- Livewire full-page view (SDD wire-backend-into-refactored-ui). Read-only
     student page: sessions + request statuses (FR 1.2/1.3). --}}

@section('title', 'Student My Timetable')

<div class="lw-page">

    <style>
        .timetable td.hour-cell.offday-slot { background: transparent; }
        .timetable td.today-cell.offday-slot { background: transparent; }
        .timetable td.hour-cell.offday-slot .cell-empty { background: transparent; }
    </style>

    <!-- ─── Page Header ─── -->
    @include('partials.ui-page-header', [
        'title' => 'Student My Timetable',
        'description' => 'View your weekly class schedule across all sessions.',
        'chips' => $cohortCode ? [['label' => $cohortCode]] : [],
    ])

    <!-- ─── Semester Progress ─── -->
    <div class="semester-progress" id="semesterProgress">
        <div class="progress-label" id="progressLabel"></div>
        <div class="progress-track"><div class="progress-fill" id="progressFill"></div></div>
    </div>

    <!-- ─── Semester Bar ─── -->
    <div class="semester-bar">
        @include('partials.ui-week-nav', ['prevOnclick' => 'prevWeek()', 'nextOnclick' => 'nextWeek()', 'selectId' => 'weekSelect', 'selectOnclick' => 'selectWeek(this.value)', 'showPrint' => true])
    </div>

    <!-- ─── Week Subtitle ─── -->
    <div class="week-subtitle" id="weekSubtitle"></div>

    <!-- ─── Grid Wrapper ─── -->
    @include('partials.ui-grid-table')
        @include('partials.ui-empty-state', ['title' => 'No classes this week', 'text' => $hasCohort ? 'All classes for this week have been cancelled.' : 'You are not assigned to a cohort yet.'])

    <!-- ─── Legend Bar ─── -->
    @include('partials.ui-legend-bar')

    <!-- ─── Weekly Summary Bar ─── -->
    @include('partials.ui-summary-bar', [
        'cards' => [
            ['class' => 'card-total', 'valueId' => 'sumTotal', 'label' => 'Total Classes',
                'description' => 'Total classes in <span class="info-keyword">your timetable</span> this week.'],
            ['class' => 'card-hours', 'valueId' => 'sumHours', 'label' => 'Class Hours',
                'description' => 'Total <span class="info-keyword">class hours</span> you have this week (each slot = <strong>30 minutes</strong>).'],
            ['class' => 'card-replacement', 'valueId' => 'sumReplacement', 'label' => 'Replacements',
                'description' => 'Classes where a <span class="info-keyword">different lecturer</span> is covering this week.'],
            ['class' => 'card-pending', 'valueId' => 'sumPending', 'label' => 'Pending',
                'description' => 'Replacement requests still <span class="warn-keyword">being processed</span> for your classes.'],
            ['class' => 'card-conflict', 'valueId' => 'sumConflict', 'label' => 'Conflicts',
                'description' => '<span class="warn-keyword">Scheduling clashes and public holidays</span> in your timetable that need attention.'],
        ]
    ])

    <!-- ═══ View-Only Modal ═══ -->
    @include('partials.ui-class-detail-modal')

    <!-- ═══ Copy Toast ═══ -->
    <div class="copy-toast" id="copyToast"></div>

    <script>
        /* Real-data bridge. DOMContentLoaded: mock-data.js / ui-common.js are
           parsed AFTER @yield('content') in the layout body, so top-level
           access here would throw before they load. */
        document.addEventListener('DOMContentLoaded', function () {
            MockData.semester = @json($semesterJs);
            MockData.holidays = @json($holidaysJs);
            /* Real viewer identity (all-pages-design-parity S3): the shared
               openClassModal compares event.requestedBy against this — without
               it the student page's comparison runs against the mock-data
               default persona and a student's own pending request never
               matches. */
            MockData.currentUser = @json(['name' => auth()->user()?->name ?? '']);

            window.weekData = generateWeekData();
            const weekData = window.weekData;
            const eventsData = @json($eventsByWeek);

            window.currentWeek = {{ (int) ($currentWeek ?? 0) }};
            let currentWeek = window.currentWeek;

            const weekNav = new WeekNavigator(MockData.semester, weekData);
            weekNav._currentWeek = currentWeek;

            function openModal(event) {
                openClassModal({
                    event: event,
                    dayIndex: event.di,
                    days: weekData[currentWeek].days,
                    title: event.code || 'Class Details'
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
                    replacementNoteFn: function (e) { return buildReplacementNote(e); }
                });
                updateSummary();
            }

            function updateSummary() {
                computeSummary((eventsData[currentWeek] || []).filter(e => e.status !== 'cancelled'), weekData[currentWeek].days);
                updateWeekArrows(currentWeek <= 0, currentWeek >= weekData.length - 1);
            }

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

            function saveWeek() { weekNav.save(); window.currentWeek = currentWeek; }

            weekNav.load();
            currentWeek = weekNav.currentWeek;

            const chipEl = document.getElementById('semesterChip');
            if (chipEl) chipEl.textContent = MockData.semester.chipText;

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

            initEvCodeCopy('copyToast');

            initGridSwipeGestures(prevWeek, nextWeek);

            /* onclick= handlers in partials resolve via window */
            window.prevWeek = prevWeek;
            window.nextWeek = nextWeek;
            window.selectWeek = selectWeek;
            window.closeModal = closeModal;
            window.closeModalOutside = closeModalOutside;
            window.buildTimetable = buildTimetable;
            window.updateSummary = updateSummary;
        });
    </script>
</div>
