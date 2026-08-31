{{-- Livewire full-page view (SDD wire-backend-into-refactored-ui). All roles;
     students pinned to own cohort (FR 1.2). Faculty/cohort selects are
     JS-owned (wire:ignore) and push cohortId to the server ($wire.set) —
     the component re-queries allEvents for the chosen cohort. --}}

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

    <!-- ─── Semester Bar (JS-owned selects → $wire.set; wire:ignore keeps the
         populated options alive across Livewire morphs) ─── -->
    <div class="semester-bar" wire:ignore>
        <select id="facultySelect" onchange="onFacultyChange(this.value)" @if($isStudent) disabled @endif>
            <option value="">Select Faculty</option>
        </select>
        <select id="cohortSelect" onchange="onCohortChange(this.value)" disabled>
            <option value="">Select Cohort</option>
        </select>
        @include('partials.ui-week-nav', ['prevOnclick' => 'prevWeek()', 'nextOnclick' => 'nextWeek()', 'selectId' => 'weekSelect', 'selectOnclick' => 'selectWeek(this.value)', 'disabled' => !$cohortId])
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
        /* ───── Real-data bridge ───── */
        MockData.semester = @json($semesterJs);
        MockData.holidays = @json($holidaysJs);
        MockData.currentUser = @json(['name' => auth()->user()?->name ?? '', 'staffId' => (string) (auth()->user()?->lecturer?->staff_id ?? '')]);

        const weekData = generateWeekData();
        let allEvents = @json($eventsByWeek);
        const facultyData = @json($faculties);
        const isStudentViewer = @json($isStudent);
        const serverCohortId = @json($cohortId);

        let currentWeek = {{ (int) ($currentWeek ?? 0) }};

        const weekNav = new WeekNavigator(MockData.semester, weekData);
        weekNav._currentWeek = currentWeek;

        /* ════════════════ DROPDOWN POPULATION ════════════════ */

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
            sel.disabled = !faculty;
        }

        function onFacultyChange(fid) {
            populateCohorts(fid);
            document.getElementById('weekSelect').disabled = true;
            $wire.$set('cohortId', null);
            showGuidance();
        }

        function onCohortChange(cid) {
            if (!cid) {
                document.getElementById('weekSelect').disabled = true;
                showGuidance();
                $wire.$set('cohortId', null);
                return;
            }
            currentWeek = currentWeekIndex();
            weekNav._currentWeek = currentWeek;
            document.getElementById('weekSelect').selectedIndex = currentWeek;
            document.getElementById('weekSelect').disabled = false;
            // Server re-queries allEvents for this cohort, then the update hook rebuilds.
            $wire.$set('cohortId', parseInt(cid, 10));
        }

        function showGuidance() {
            document.getElementById('emptyState').style.display = 'flex';
            document.getElementById('emptyTitle').textContent = 'Select a faculty first';
            document.getElementById('emptyText').textContent = 'Choose a faculty, then pick a cohort to view its weekly timetable.';
            document.getElementById('timetable').querySelector('thead').innerHTML = '';
            document.getElementById('timetable').querySelector('tbody').innerHTML = '';
            ['sumTotal','sumHours','sumReplacement','sumPending','sumConflict'].forEach(id => {
                const el = document.getElementById(id);
                if (el) el.textContent = '0';
            });
            updateWeekArrows(true, true);
        }

        /* ════════════════ WEEK NAVIGATION ════════════════ */

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

        /* ════════════════ GRID ════════════════ */

        function buildTimetable() {
            if (!serverCohortId && !$wire.cohortId) {
                showGuidance();
                return;
            }

            const weekEvents = allEvents[currentWeek] || [];

            if (weekEvents.length === 0) {
                document.getElementById('emptyState').style.display = 'flex';
                document.getElementById('emptyTitle').textContent = 'No classes scheduled';
                document.getElementById('emptyText').textContent = 'No classes scheduled for this cohort in the selected week.';
                ['sumTotal','sumHours','sumReplacement','sumPending','sumConflict'].forEach(id => {
                    document.getElementById(id).textContent = '0';
                });
                updateWeekArrows(currentWeek <= 0, currentWeek >= weekData.length - 1);
                return;
            }

            document.getElementById('emptyState').style.display = 'none';

            buildTimetableGrid({
                events: weekEvents,
                days: weekData[currentWeek].days,
                onEventClick: function(e, di) { openModal(e, di); },
                tooltipExtra: function(e) { return e.lecturer || '—'; },
                statusClassFn: function(div, e, isConflict) {
                    if (isConflict) { div.classList.add('event-public-holiday'); return; }
                    const isMine = e.isMine === true;
                    if (e.status === 'pending') {
                        div.classList.add(isMine ? 'event-mine-pending' : 'event-others-pending');
                    } else {
                        div.classList.add(isMine ? 'event-mine' : 'event-others');
                    }
                },
                replacementNoteFn: function(e) {
                    return buildReplacementNote(e, { checkOwnership: function(ev) { return ev.isMine === true; } });
                }
            });
            computeSummary(weekEvents, weekData[currentWeek].days);
            updateWeekArrows(currentWeek <= 0, currentWeek >= weekData.length - 1);
        }

        /* ════════════════ MODAL ════════════════ */

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

        /* ════════════════ INIT ════════════════ */

        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('semesterChip').textContent = MockData.semester.chipText;
            populateWeekSelect('weekSelect', { ranges: false, selected: currentWeek });
            populateFaculties();

            if (isStudentViewer) {
                // FR 1.2: students are pinned — preselect and lock the selects.
                if (serverCohortId) {
                    const facultySel = document.getElementById('facultySelect');
                    facultySel.value = String(@json($facultyId));
                    populateCohorts(@json($facultyId));
                    document.getElementById('cohortSelect').value = String(serverCohortId);
                    buildTimetable();
                } else {
                    showGuidance();
                }
            } else {
                showGuidance();
            }
        });

        /* Server roundtrip finished (cohort switch) → refresh client data + grid */
        document.addEventListener('livewire:updated', () => {
            allEvents = @json($eventsByWeek);
            if ($wire.cohortId) {
                buildTimetable();
            }
        });

        document.getElementById('todayBtn')?.addEventListener('click', goToday);
        initWeekKeyboardShortcuts();

        initTimetableKeyboardHandlers({
            prevWeek: prevWeek,
            nextWeek: nextWeek,
            openModal: openModal,
            modalId: 'eventModal'
        });

        initGridSwipeGestures(prevWeek, nextWeek);
    </script>
</div>
