{{-- Livewire full-page view (SDD wire-backend-into-refactored-ui). All roles;
     students receive only their own cohort's events server-side (FR 1.2).
     Faculty/cohort selects are JS-owned and switch client-side over the
     preloaded eventsByCohort payload — no server roundtrip needed. --}}

@section('title', 'Cohort Timetable — Class Replacement System')

<div class="lw-page">

    <style>
        /* ───── Page styles (D2) ───── */
        .semester-bar select:disabled,
        .semester-bar select:disabled:hover {
            opacity: 0.5;
            cursor: var(--cursor-cancel);
            background: var(--color-surface-variant);
            color: var(--color-on-surface-variant);
        }
        @media (max-width: 1024px) {
            .grid-scroll { overflow-x: auto; }
        }
        @media (max-width: 768px) {
            .semester-bar select:not(.week-select) {
                min-width: 0;
                flex: 1 1 120px;
            }
            .week-nav { width: 100%; }
        }
        .timetable td.hour-cell.offday-slot { background: transparent; }
        .timetable td.today-cell.offday-slot { background: transparent; }
        .timetable td.hour-cell.offday-slot .cell-empty { background: transparent; }
    </style>

    <!-- ─── Page Header ─── -->
    @include('partials.ui-page-header', ['title' => 'Cohort Timetable', 'description' => 'View the weekly timetable for any cohort across all faculties.'])

    @include('partials.ui-guide-block', [
        'guideTitle' => 'How to use this page',
        'guideItems' => [
            '<strong>Select cohort</strong> — choose a faculty, then a cohort to view its timetable',
            '<strong>Week navigation</strong> — use arrows or Today button to browse weeks',
            '<strong>Slot status</strong> — Blue: Your classes, Green: Others\' classes, Grey: Others\' pending, Amber: Your pending, Red: Conflict',
            '<strong>View details</strong> — click any slot to see class details and venue info',
        ]
    ])

    <!-- ─── Semester Bar ─── -->
    <div class="semester-bar">
        <select id="facultySelect" onchange="onFacultyChange(this.value)" @if($isStudent) disabled @endif>
            <option value="">Select Faculty</option>
        </select>
        <select id="cohortSelect" onchange="onCohortChange(this.value)" disabled>
            <option value="">Select Cohort</option>
        </select>
        @include('partials.ui-week-nav', ['prevOnclick' => 'prevWeek()', 'nextOnclick' => 'nextWeek()', 'selectId' => 'weekSelect', 'selectOnclick' => 'selectWeek(this.value)', 'disabled' => $pinnedCohortId === null])
    </div>

    <!-- ─── Grid Wrapper ─── -->
    @include('partials.ui-grid-table')

    <!-- ─── Legend Bar ─── -->
    @include('partials.ui-legend-bar', [
        'items' => [
            ['color' => 'var(--color-primary-container)', 'label' => 'Your Classes', 'tip' => 'Normal or replacement sessions assigned to you'],
            ['color' => 'var(--color-success-container)', 'label' => 'Others\' Classes', 'tip' => 'Normal or replacement sessions by other lecturers'],
            ['color' => 'var(--color-surface-variant)', 'label' => 'Others\' Pending', 'tip' => 'Replacement request by other lecturers, awaiting PL approval'],
            ['color' => 'var(--color-tertiary-container)', 'label' => 'Your Pending', 'tip' => 'Your replacement request, awaiting PL approval'],
            ['color' => 'var(--color-error-container)', 'label' => 'Conflict', 'tip' => 'Scheduling conflict or public holiday'],
        ]
    ])

    <!-- ─── Summary Bar ─── -->
    @include('partials.ui-summary-bar', [
        'cards' => [
            ['class' => 'card-total', 'valueId' => 'sumTotal', 'label' => 'Total Classes',
                'description' => 'Total classes scheduled for <strong>this cohort</strong> in the selected week.'],
            ['class' => 'card-hours', 'valueId' => 'sumHours', 'label' => 'Teaching Hours',
                'description' => 'Total <strong>teaching hours</strong> for this cohort in the selected week (each slot = <strong>30 minutes</strong>).'],
            ['class' => 'card-replacement', 'valueId' => 'sumReplacement', 'label' => 'Replacements',
                'description' => 'Classes with a <strong>replacement lecturer</strong> assigned this week.'],
            ['class' => 'card-pending', 'valueId' => 'sumPending', 'label' => 'Pending',
                'description' => 'Replacement requests for this cohort still <strong>waiting for approval</strong> or a volunteer.'],
            ['class' => 'card-conflict', 'valueId' => 'sumConflict', 'label' => 'Conflicts',
                'description' => '<strong>Scheduling clashes</strong> or classes on <strong>public holidays</strong> for this cohort that need attention.'],
        ]
    ])

    <!-- ─── Empty State ─── -->
    @include('partials.ui-empty-state', ['title' => 'Select a faculty first', 'text' => 'Choose a faculty, then pick a cohort to view its weekly timetable.'])

    <!-- ─── Event Modal ─── -->
    @include('partials.ui-class-detail-modal', ['modalId' => 'eventModal'])

    <script>
        /* ───── Real-data bridge. DOMContentLoaded: mock-data.js / ui-common.js
           are parsed AFTER @yield('content') in the layout body, so top-level
           access here would throw before they load. ───── */
        document.addEventListener('DOMContentLoaded', function () {
            MockData.semester = @json($semesterJs);
            MockData.holidays = @json($holidaysJs);
            MockData.currentUser = @json(['name' => auth()->user()?->name ?? '', 'staffId' => (string) (auth()->user()?->lecturer?->staff_id ?? '')]);

            window.weekData = generateWeekData();
            const weekData = window.weekData;
            const eventsByCohort = @json($eventsByCohort);
            const facultyData = @json($faculties);
            const isStudentViewer = @json($isStudent);
            const pinnedCohortId = @json($pinnedCohortId);

            window.currentWeek = {{ (int) ($currentWeek ?? 0) }};
            let currentWeek = window.currentWeek;
            let selectedCohortId = pinnedCohortId;

            const weekNav = new WeekNavigator(MockData.semester, weekData);
            weekNav._currentWeek = currentWeek;

            /* ════════ DROPDOWNS ════════ */

            function populateFaculties() {
                const sel = document.getElementById('facultySelect');
                sel.innerHTML = '<option value="">Select Faculty</option>' +
                    facultyData.map(f => `<option value="${f.id}">${f.name}</option>`).join('');
            }

            function populateCohorts(fid) {
                const sel = document.getElementById('cohortSelect');
                const faculty = facultyData.find(f => String(f.id) === String(fid));
                sel.innerHTML = '<option value="">Select Cohort</option>' +
                    (faculty ? faculty.cohorts.map(c => `<option value="${c.id}">${c.name}</option>`).join('') : '');
                sel.disabled = !faculty || isStudentViewer;
            }

            function onFacultyChange(fid) {
                populateCohorts(fid);
                document.getElementById('weekSelect').disabled = true;
                selectedCohortId = null;
                showGuidance();
            }

            function onCohortChange(cid) {
                selectedCohortId = cid ? parseInt(cid, 10) : null;
                document.getElementById('weekSelect').disabled = !selectedCohortId;
                if (!selectedCohortId) {
                    showGuidance();
                    return;
                }
                currentWeek = currentWeekIndex();
                weekNav._currentWeek = currentWeek;
                document.getElementById('weekSelect').selectedIndex = currentWeek;
                buildTimetable();
            }

            window.onFacultyChange = onFacultyChange;
            window.onCohortChange = onCohortChange;

            function showGuidance() {
                document.getElementById('emptyState').style.display = 'flex';
                document.getElementById('emptyTitle').textContent = 'Select a faculty first';
                document.getElementById('emptyText').textContent = 'Choose a faculty, then pick a cohort to view its weekly timetable.';
                document.getElementById('tableHead').innerHTML = '';
                document.getElementById('tableBody').innerHTML = '';
                ['sumTotal', 'sumHours', 'sumReplacement', 'sumPending', 'sumConflict'].forEach(id => {
                    document.getElementById(id).textContent = '0';
                });
                updateWeekArrows(true, true);
            }

            /* ════════ WEEK NAVIGATION ════════ */

            function prevWeek() {
                weekNav.prevWeek();
                currentWeek = weekNav.currentWeek;
                buildTimetable();
            }

            function nextWeek() {
                weekNav.nextWeek();
                currentWeek = weekNav.currentWeek;
                buildTimetable();
            }

            function selectWeek(index) {
                weekNav.selectWeek(parseInt(index, 10));
                currentWeek = weekNav.currentWeek;
                buildTimetable();
            }

            function goToday() {
                weekNav.jumpToToday();
                currentWeek = weekNav.currentWeek;
                buildTimetable();
            }

            /* ════════ GRID ════════ */

            function updateSummary() {
                const weekEvents = (eventsByCohort[selectedCohortId] || [])[currentWeek] || [];
                computeSummary(weekEvents, weekData[currentWeek].days);
                updateWeekArrows(currentWeek <= 0, currentWeek >= weekData.length - 1);
            }

            function buildTimetable() {
                currentWeek = weekNav.currentWeek;
                if (!selectedCohortId) {
                    showGuidance();
                    return;
                }

                const weekEvents = (eventsByCohort[selectedCohortId] || [])[currentWeek] || [];

                if (weekEvents.length === 0) {
                    document.getElementById('emptyState').style.display = 'flex';
                    document.getElementById('emptyTitle').textContent = 'No classes scheduled';
                    document.getElementById('emptyText').textContent = 'No classes scheduled for this cohort in the selected week.';
                    ['sumTotal', 'sumHours', 'sumReplacement', 'sumPending', 'sumConflict'].forEach(id => {
                        document.getElementById(id).textContent = '0';
                    });
                    updateWeekArrows(currentWeek <= 0, currentWeek >= weekData.length - 1);
                    return;
                }

                document.getElementById('emptyState').style.display = 'none';

                buildTimetableGrid({
                    events: weekEvents,
                    days: weekData[currentWeek].days,
                    onEventClick: function (e, di) { openModal(e, di); },
                    tooltipExtra: function (e) { return e.lecturer || '—'; },
                    statusClassFn: function (div, e, isConflict) {
                        if (isConflict) { div.classList.add('event-public-holiday'); return; }
                        if (e.status === 'conflict') {
                            // B005 diploma-cohort conflict (b005-diploma-conflict): owner loud, others quiet red (upstream idiom)
                            div.classList.add(e.lecturer === MockData.currentUser.name ? 'event-conflict' : 'event-public-holiday');
                            return;
                        }
                        const isMine = e.isMine === true;
                        if (e.status === 'pending') {
                            div.classList.add(isMine ? 'event-mine-pending' : 'event-others-pending');
                        } else {
                            div.classList.add(isMine ? 'event-mine' : 'event-others');
                        }
                    },
                    replacementNoteFn: function (e) {
                        return buildReplacementNote(e, { checkOwnership: function (ev) { return ev.isMine === true; } });
                    }
                });
                computeSummary(weekEvents, weekData[currentWeek].days);
                updateWeekArrows(currentWeek <= 0, currentWeek >= weekData.length - 1);
            }

            /* ════════ MODAL ════════ */

            function openModal(event, di) {
                openClassModal({
                    event: event,
                    dayIndex: (di !== undefined ? di : event.di),
                    days: weekData[currentWeek].days,
                    modalId: 'eventModal',
                    extraFields: [
                        { label: 'Cohort', value: event.cohort || '—' }
                    ]
                });
            }

            function closeModal() {
                document.getElementById('eventModal').style.display = 'none';
            }

            function closeModalOutside(e) {
                closeOnOverlayClick(e, closeModal);
            }

            closeOnEsc(closeModal);

            /* ════════ INIT ════════ */

            document.getElementById('semesterChip').textContent = MockData.semester.chipText;
            populateWeekSelect('weekSelect', { ranges: false, selected: currentWeek });
            populateFaculties();

            if (selectedCohortId) {
                // Students arrive pinned (FR 1.2): preselect faculty + cohort, lock selects.
                const facultySel = document.getElementById('facultySelect');
                const facultyOfCohort = facultyData.find(f =>
                    f.cohorts.some(c => String(c.id) === String(selectedCohortId)));
                if (facultyOfCohort) {
                    facultySel.value = String(facultyOfCohort.id);
                    populateCohorts(facultyOfCohort.id);
                    document.getElementById('cohortSelect').value = String(selectedCohortId);
                }
                document.getElementById('weekSelect').disabled = false;
                buildTimetable();
            } else {
                showGuidance();
            }

            document.getElementById('todayBtn')?.addEventListener('click', goToday);
            initWeekKeyboardShortcuts();

            initTimetableKeyboardHandlers({
                prevWeek: prevWeek,
                nextWeek: nextWeek,
                openModal: openModal,
                modalId: 'eventModal'
            });

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
