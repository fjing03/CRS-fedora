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
            '<strong>Auto-selected</strong> — your faculty and the first cohort you teach load on arrival; switch the dropdowns to view any cohort\'s timetable',
            '<strong>Week navigation</strong> — use arrows or Today button to browse weeks',
            '<strong>Slot status</strong> — Green: scheduled class, Amber: pending replacement, Red: conflict/public holiday; thick border (3px) = your classes, thin border = others\'',
            '<strong>View details</strong> — click any slot to see class details and venue info',
            '<strong>Full names</strong> — hover the faculty/cohort dropdowns to see them spelled out',
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
        @include('partials.ui-week-nav', ['prevOnclick' => 'prevWeek()', 'nextOnclick' => 'nextWeek()', 'selectId' => 'weekSelect', 'selectOnclick' => 'selectWeek(this.value)', 'disabled' => $pinnedCohortId === null, 'showPrint' => true])
    </div>

    <!-- ─── Grid Wrapper ─── -->
    @include('partials.ui-grid-table')

    <!-- ─── Legend Bar ─── frozen 3-item block-class legend + ownership hint (all-pages-design-parity) -->
    @include('partials.ui-legend-bar', [
        'ownershipHint' => true,
        'items' => [
            ['class' => 'event-normal', 'label' => 'Normal', 'tip' => 'Scheduled class with no issues (replacement sessions fold in here on this page)'],
            ['class' => 'event-pending', 'label' => 'Pending', 'tip' => 'Replacement request awaiting PL approval'],
            ['class' => 'event-conflict', 'label' => 'Conflict / Public Holiday', 'tip' => 'This class will not run as scheduled — scheduling conflict or public holiday'],
        ]
    ])

    <!-- ─── Summary Bar ─── frozen 5-card set (all-pages-design-parity C2) -->
    @include('partials.ui-summary-bar', [
        'cards' => [
            ['class' => 'card-total', 'valueId' => 'sumTotal', 'label' => 'Total Classes',
                'description' => 'Total classes scheduled for <span class="info-keyword">this cohort</span> in the selected week.'],
            ['class' => 'card-hours', 'valueId' => 'sumHours', 'label' => 'Teaching Hours',
                'description' => 'Total <span class="info-keyword">teaching hours</span> for this cohort in the selected week (each slot = <strong>30 minutes</strong>).'],
            ['class' => 'card-replacement', 'valueId' => 'sumMyClasses', 'label' => 'My Teaching Classes',
                'description' => 'Sessions <span class="info-keyword">taught by you</span> this week — <strong>each class counts separately</strong> (e.g. Subject A (T) and Subject A (L) are two classes).'],
            ['class' => 'card-hours', 'valueId' => 'sumMyHours', 'label' => 'My Teaching Hours',
                'description' => 'Total hours of <span class="info-keyword">your classes</span> this week (each slot = <strong>30 minutes</strong>).'],
            ['class' => 'card-conflict', 'valueId' => 'sumConflict', 'label' => 'Conflicts',
                'description' => '<span class="warn-keyword">Scheduling clashes</span> or classes on <strong>public holidays</strong> for this cohort that need attention.'],
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
            const defaultFacultyId = @json($defaultFacultyId);
            const taughtCohortIds = @json($taughtCohortIds);

            window.currentWeek = {{ (int) ($currentWeek ?? 0) }};
            let currentWeek = window.currentWeek;
            let selectedCohortId = pinnedCohortId;

            const weekNav = new WeekNavigator(MockData.semester, weekData, null, 'cohortTimetableWeek');
            weekNav._currentWeek = currentWeek;

            /* ════════ DROPDOWNS ════════ */

            function populateFaculties() {
                const sel = document.getElementById('facultySelect');
                sel.innerHTML = '<option value="">Select Faculty</option>' +
                    facultyData.map(f => `<option value="${f.id}">${f.name}</option>`).join('');
            }

            /* Hover tooltips (user decision 2026-10-13): the selects carry
               data-tip, which the shared hover-delegation tooltip reads live —
               so refreshing the attribute on every change is enough. Full
               labels come from the payload's 'full' fields. */
            function refreshTips() {
                const fsel = document.getElementById('facultySelect');
                const csel = document.getElementById('cohortSelect');
                const f = facultyData.find(x => String(x.id) === String(fsel.value));
                if (f) fsel.setAttribute('data-tip', f.full);
                else fsel.removeAttribute('data-tip');
                const c = f ? f.cohorts.find(x => String(x.id) === String(csel.value)) : null;
                if (c) csel.setAttribute('data-tip', c.full);
                else csel.removeAttribute('data-tip');
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
                refreshTips();
                showGuidance();
            }

            function onCohortChange(cid) {
                selectedCohortId = cid ? parseInt(cid, 10) : null;
                document.getElementById('weekSelect').disabled = !selectedCohortId;
                refreshTips();
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
                ['sumTotal', 'sumHours', 'sumMyClasses', 'sumMyHours', 'sumConflict'].forEach(id => {
                    document.getElementById(id).textContent = '0';
                });
                /* summary cards hidden whenever no timetable is shown (user
                   decision 2026-10-13 — cards full of zeros are just noise) */
                document.getElementById('summarySection').style.display = 'none';
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
                    ['sumTotal', 'sumHours', 'sumMyClasses', 'sumMyHours', 'sumConflict'].forEach(id => {
                        document.getElementById(id).textContent = '0';
                    });
                    document.getElementById('summarySection').style.display = 'none';
                    updateWeekArrows(currentWeek <= 0, currentWeek >= weekData.length - 1);
                    return;
                }

                document.getElementById('emptyState').style.display = 'none';
                document.getElementById('summarySection').style.display = '';

                buildTimetableGrid({
                    events: weekEvents,
                    days: weekData[currentWeek].days,
                    onEventClick: function (e, di) { openModal(e, di); },
                    statusClassFn: function (div, e, isConflict) {
                        var isMine = e.lecturer === MockData.currentUser.name;
                        /* §10.0 two-axis language: colour = status (same fill for
                           everyone), border weight = ownership — own conflict/PH
                           gets the loud 3px error border; everyone else's is the
                           same red with a 0.5px hairline. (all-pages-design-parity
                           C4/C5: frozen idiom — the old e.isMine test read a field
                           no payload ever set, so everything rendered as others'.) */
                        if ((isConflict || e.status === 'conflict') && isMine) {
                            div.classList.add('event-conflict');
                        } else if (isConflict || e.status === 'conflict') {
                            div.classList.add('event-public-holiday');   // same error red, hairline border (not yours)
                        } else if (e.status === 'pending') {
                            div.classList.add(isMine ? 'event-mine-pending' : 'event-others-pending');
                        } else {
                            div.classList.add(isMine ? 'event-mine' : 'event-others');
                        }
                    },
                    replacementNoteFn: function (e) {
                        return buildReplacementNote(e, {
                            checkOwnership: function (ev) { return ev.lecturer === MockData.currentUser.name; }
                        });
                    }
                });
                computeSummary(weekEvents.filter(e => e.status !== 'cancelled'), weekData[currentWeek].days);
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
                refreshTips();
                buildTimetable();
            } else if (!isStudentViewer && defaultFacultyId) {
                /* Staff auto-select (user decisions 2026-10-13): land on the
                   lecturer's own department's faculty, with the FIRST cohort
                   they actually teach (displayed sort order) pre-picked — the
                   grid + week nav are live on arrival, zero clicks. When they
                   teach nothing in that faculty, everything stays unselected
                   and the guidance shows (predictable fallback). */
                const fac = facultyData.find(f => String(f.id) === String(defaultFacultyId));
                if (fac) {
                    document.getElementById('facultySelect').value = String(fac.id);
                    populateCohorts(fac.id);
                    const mine = fac.cohorts.find(c => taughtCohortIds.includes(c.id));
                    if (mine) {
                        document.getElementById('cohortSelect').value = String(mine.id);
                        onCohortChange(String(mine.id));
                    } else {
                        refreshTips();
                        showGuidance();
                    }
                } else {
                    refreshTips();
                    showGuidance();
                }
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
