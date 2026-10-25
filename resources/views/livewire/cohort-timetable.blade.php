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

        /* ───── SelectDd panel (venue-dd idiom on the semester-bar selects) ─────
           The native <select> stays fully functional (onchange wiring, keyboard
           arrows, Playwright specs); the mouse popup is replaced by a DOM panel
           whose rows carry data-tip — so the SHARED hover tooltip (not the
           browser's native title box) explains every option. Taught cohorts /
           faculties are marked bold blue + ✓ (taughtCohortIds from the payload;
           ownership axis, deliberately a different colour from the green/amber/
           red status axis). Generic .select-dd-* names for later promotion to a
           shared partial when a 3rd custom dropdown appears. */
        .select-dd-panel {
            position: fixed;
            display: none;
            background: var(--color-surface);
            border: 1px solid var(--color-outline);
            border-radius: var(--radius-md);
            box-shadow: 0 4px 16px rgba(0,0,0,0.12), 0 1px 4px rgba(0,0,0,0.06);
            z-index: 1000;
            padding: 4px 0;
            min-width: 180px;
            max-width: calc(100vw - 16px);
            max-height: 320px;
            overflow-y: auto;
            scrollbar-width: thin;
            overscroll-behavior: contain; /* wheel at list end must NOT chain-scroll
               the page behind — that page scroll used to close the panel */
        }
        .select-dd-item {
            display: flex;
            align-items: center;
            padding: 7px 12px;
            font-size: 13px;
            font-weight: 500;
            color: var(--color-on-surface);
            cursor: pointer;
            white-space: nowrap;
            user-select: none;
        }
        .select-dd-item:hover { background: var(--color-surface-variant); }
        .select-dd-item-taught,
        .select-dd-item-taught::before {
            color: var(--color-primary);
            font-weight: 700;
        }
        .select-dd-item-taught::before { content: '✓ '; }
        .select-dd-hint {
            position: sticky; /* stays visible while a long list scrolls */
            bottom: 0;
            margin-top: 4px;
            padding: 6px 12px 5px;
            border-top: 1px solid var(--color-outline);
            background: var(--color-surface);
            font-size: 11px;
            color: var(--color-on-surface-variant);
        }
        /* open-state ring on the trigger (box-shadow only — the selects are
           borderless, so a border would shift layout by a pixel) */
        .semester-bar select.select-dd-open {
            box-shadow: 0 0 0 1.5px var(--color-primary);
        }
        /* native-option fallback for the keyboard-only Alt+Down popup */
        .semester-bar select:not(.week-select) option[data-taught] {
            font-weight: 700;
            color: var(--color-primary);
        }
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
            '<strong>✓ Your cohorts</strong> — bold blue entries marked ✓ are the cohorts/faculties you teach this semester',
        ]
    ])

    <!-- ─── Semester Bar ─── -->
    <div class="semester-bar">
        <select id="facultySelect" onchange="onFacultyChange(this.value, { origin: 'user' })" @if($isStudent) disabled @endif>
            <option value="">Select Faculty</option>
        </select>
        <select id="cohortSelect" onchange="onCohortChange(this.value, { origin: 'user' })" disabled>
            <option value="">Select Cohort</option>
        </select>
        @include('partials.ui-week-nav', ['prevOnclick' => 'prevWeek()', 'nextOnclick' => 'nextWeek()', 'selectId' => 'weekSelect', 'selectOnclick' => 'selectWeek(this.value)', 'disabled' => $pinnedCohortId === null, 'showPrint' => true])
    </div>

    <!-- ─── Grid Wrapper ─── -->
    @include('partials.ui-grid-table')

    <!-- ─── Legend Bar ─── frozen 4-item block-class legend (the user's Replacement chip, 2026-10-25) + ownership hint (all-pages-design-parity) -->
    @include('partials.ui-legend-bar', [
        'ownershipHint' => true,
        'items' => [
            ['class' => 'event-normal', 'label' => 'Normal', 'tip' => 'Scheduled class with no issues'],
            ['class' => 'event-replacement', 'label' => 'Replacement', 'tip' => 'Approved replacement session — the class ran at its NEW slot (an approved request renders here as the dark block)'],
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

    <!-- ─── Event Modal (SDD cohort-venue-replace-now: the user's law
         2026-10-24 "Cohort page & Venue page shud've Replace Now button too.
         for OWN Original Conflicted Class" — the footer gains the venue's
         Replace Now button module (gated at openModal: OWN + conflict) and
         KEEPS the runtime-appended Cancel Class? button. SUPERSEDES the
         class-modal-affordances frozen D1/D3 "no Replace Now on cohort"
         decision.) ─── -->
    @section('modal-footer')
        <div class="modal-footer-left">
            <button class="btn-close-modal" onclick="closeModal()">Close</button>
        </div>
        <div class="modal-footer-right">
            <button class="btn-replace-now" id="btnReplaceNow" style="display:none" onclick="goToReplacementFor(currentModalEvent?.id, currentWeek + 1, 'cohort-timetable')">Replace Now</button>
            <!-- the runtime-appended Cancel Class? lives here (CancelClass.renderButton) -->
        </div>
    @endsection
    @include('partials.ui-class-detail-modal', ['modalId' => 'eventModal'])
    @include('partials.ui-cancel-class-modal')

    <script>
        /* ───── Real-data bridge. DOMContentLoaded: mock-data.js / ui-common.js
           are parsed AFTER @yield('content') in the layout body, so top-level
           access here would throw before they load. ───── */
        document.addEventListener('DOMContentLoaded', function () {
            MockData.semester = @json($semesterJs);
            MockData.mockNow = null; /* REAL clock — the demo anchor is mock-era only (2026-10-15 vine-line); the portfolio's today must be correct */
            MockData.holidays = @json($holidaysJs);
            MockData.currentUser = @json(['name' => auth()->user()?->name ?? '', 'staffId' => (string) (auth()->user()?->lecturer?->staff_id ?? '')]);
            /* the my-timetable/venue window channel (SDD cohort-venue-replace-now
               T1, review R1 blocker): BEFORE this init the cohort page never
               set window.currentModalEvent — the button's click
               (goToReplacementFor(currentModalEvent?.id, …)) threw
               ReferenceError: the optional chain guards the PROPERTY, not the
               unbound identifier. my-timetable :138 / venue :218 = precedents. */
            window.currentModalEvent = null;

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

            /* week-memory-until-logout (T5): the session-scoped mode — the
               per-login stamp binds this page's week memory to the login
               session; the load() below is the page's FIRST read (it runs
               before the first populateWeekSelect/buildTimetable so the
               first paint consumes the restored position); the saves already
               funnel the class methods. The faculty-switch re-anchor
               quirk (re-lands today on a switch) stays deliberate. */
            const weekNav = new WeekNavigator(MockData.semester, weekData, null, 'cohortTimetableWeek', null, { sessionScoped: true, loginStamp: @json(session('login_week_stamp')) });
            weekNav._currentWeek = currentWeek;   /* the server's today family FIRST (the fresh-visit default) */
            weekNav.load();                        /* the session override: the saved version wins when it exists */
            currentWeek = weekNav.currentWeek;
            window.currentWeek = currentWeek;

            /* ════════ DROPDOWNS ════════ */

            /* Faculties where the logged-in lecturer teaches >=1 cohort —
               derived client-side from taughtCohortIds (no new payload). */
            function taughtFacultyIds() {
                return facultyData
                    .filter(f => f.cohorts.some(c => taughtCohortIds.includes(c.id)))
                    .map(f => f.id);
            }

            function populateFaculties() {
                const sel = document.getElementById('facultySelect');
                const tFids = taughtFacultyIds();
                sel.innerHTML = '<option value="">Select Faculty</option>' +
                    facultyData.map(f => `<option value="${f.id}" title="${f.full}"${tFids.includes(f.id) ? ' data-taught' : ''}>${f.name}</option>`).join('');
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
                    (faculty ? faculty.cohorts.map(c => `<option value="${c.id}" title="${c.full}"${taughtCohortIds.includes(c.id) ? ' data-taught' : ''}>${c.name}</option>`).join('') : '');
                sel.disabled = !faculty || isStudentViewer;
            }

            function facultySelectValue() {
                const fs = document.getElementById('facultySelect');
                return fs ? fs.value : '';
            }

            function onFacultyChange(fid, origin) {
                populateCohorts(fid);
                document.getElementById('weekSelect').disabled = true;
                selectedCohortId = null;
                refreshTips();
                /* Auto-pick (user decision 2026-10-13): switching faculty
                   behaves exactly like arrival — the FIRST cohort the lecturer
                   teaches in that faculty (displayed order) is pre-picked and
                   the grid goes live, zero extra clicks. Teaches nothing here
                   → the same predictable fallback as on load: guidance stays
                   and the cohort list waits. (Faculty select is disabled for
                   students, so this only ever runs for staff.) */
                const fac = facultyData.find(f => String(f.id) === String(fid));
                const mine = fac ? fac.cohorts.find(c => taughtCohortIds.includes(c.id)) : null;
                if (mine) {
                    document.getElementById('cohortSelect').value = String(mine.id);
                    onCohortChange(String(mine.id), { origin: 'programmatic' });
                } else {
                    showGuidance();
                }
            }

            function onCohortChange(cid, origin) {
                selectedCohortId = cid ? parseInt(cid, 10) : null;
                document.getElementById('weekSelect').disabled = !selectedCohortId;
                refreshTips();
                /* last-visit-dd-memory (D3): the USER-origin pick remembers
                   the pair; the programmatic paths (the arrival default, the
                   faculty-switch auto-pick, the restore) NEVER write. */
                if (cid && (origin || {}).origin === 'user' && !isStudentViewer) {
                    LastVisit.remember('cohortPage', { f: facultySelectValue(), c: parseInt(cid, 10) }, MockData.currentUser.staffId);
                }
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

            /* ════════ SELECT DD PANEL ════════
               Mouse popup replacement for the two selects: the native control
               stays real & functional (inline onchange, keyboard arrows, the
               Playwright specs' selectOption) but mousedown is preventDefaulted
               and a DOM panel opens instead — its rows carry data-tip, so the
               SHARED hover tooltip (ui-common initDataTipTooltips) explains each
               option, consistent with the venue-dd room rows. Rows are rebuilt
               from the LIVE <option>s on every open (immune to populate*()
               rewrites); taught cohorts/faculties are bold blue + ✓. */
            const selectDdPanels = [];
            let lastSelectDdOpenAt = 0;

            function closeSelectDd() {
                selectDdPanels.forEach(p => {
                    p.panel.style.display = 'none';
                    p.sel.classList.remove('select-dd-open');
                });
                /* hide the shared tooltip too — rows vanish with the panel, so
                   its mouseleave never fires and a stale tip would linger */
                const tip = document.querySelector('.data-tip-tooltip');
                if (tip) { tip.style.opacity = '0'; tip.style.visibility = 'hidden'; }
            }

            function buildSelectDd(sel, isCohortSel) {
                if (!sel) return;
                const panel = document.createElement('div');
                panel.className = 'select-dd-panel';
                document.body.appendChild(panel);
                selectDdPanels.push({ panel: panel, sel: sel });

                sel.addEventListener('mousedown', function (e) {
                    if (sel.disabled) return;   // students / no faculty chosen yet
                    e.preventDefault();          // kills the OS popup, keeps focus
                    const wasOpen = panel.style.display === 'block';
                    closeSelectDd();
                    if (wasOpen) return;

                    panel.innerHTML = '';
                    const tFids = isCohortSel ? null : taughtFacultyIds();
                    const opts = Array.prototype.slice.call(sel.options).filter(o => o.value);
                    const marks = opts.map(o => isCohortSel
                        ? taughtCohortIds.includes(parseInt(o.value, 10))
                        : tFids.includes(parseInt(o.value, 10)));
                    opts.forEach((o, i) => {
                        const row = document.createElement('div');
                        row.className = 'select-dd-item' + (marks[i] ? ' select-dd-item-taught' : '');
                        row.setAttribute('data-tip', o.title || o.textContent);
                        row.textContent = o.textContent;
                        row.addEventListener('click', function () {
                            sel.value = o.value;
                            // fires the inline onchange exactly like a native pick
                            sel.dispatchEvent(new Event('change', { bubbles: true }));
                            closeSelectDd();
                        });
                        panel.appendChild(row);
                    });
                    if (marks.some(Boolean)) {
                        const hint = document.createElement('div');
                        hint.className = 'select-dd-hint';
                        hint.textContent = isCohortSel ? '✓ bold = cohorts you teach' : '✓ bold = you teach here';
                        panel.appendChild(hint);
                    }

                    // position under the trigger, clamped to the viewport (flip up if no room below)
                    const r = sel.getBoundingClientRect();
                    panel.style.display = 'block';
                    const pw = Math.min(panel.offsetWidth, window.innerWidth - 8);
                    const ph = panel.offsetHeight;
                    let top = r.bottom + 4;
                    if (top + ph > window.innerHeight - 4 && r.top - ph - 4 >= 4) top = r.top - ph - 4;
                    panel.style.left = Math.max(4, Math.min(r.left, window.innerWidth - pw - 4)) + 'px';
                    panel.style.top = top + 'px';
                    sel.classList.add('select-dd-open');
                    lastSelectDdOpenAt = Date.now();
                });
            }

            buildSelectDd(document.getElementById('facultySelect'), false);
            buildSelectDd(document.getElementById('cohortSelect'), true);

            // close affordances: outside click, Escape, page scroll/resize
            document.addEventListener('mousedown', function (e) {
                const t = e.target;
                const onPanel = t.closest && t.closest('.select-dd-panel');
                const onTrigger = selectDdPanels.some(p => p.sel === t);
                if (!onPanel && !onTrigger) closeSelectDd();
            });
            document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeSelectDd(); });
            /* Scroll closes the panel — EXCEPT the panel's own scrollbar (user
               bug 2026-10-13): the capture-phase listener killed the first
               wheel tick inside the list, so long lists could never be scrolled,
               and trackpad momentum kept firing afterwards, making the next
               reopen clicks look dead until a hard refresh. Only ancestor/page
               scrolls close it now, and queued scroll-into-view events in the
               first 300ms after opening (a click that re-anchors the page) are
               ignored so the panel can't be killed by its own open. */
            document.addEventListener('scroll', function (e) {
                const t = e.target;
                if (t && t.closest && t.closest('.select-dd-panel')) return;
                if (Date.now() - lastSelectDdOpenAt < 300) return;
                closeSelectDd();
            }, true);
            window.addEventListener('resize', closeSelectDd);

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
                window.currentWeek = currentWeek;   /* SDD cohort-venue-replace-now T1: the venue page's window-channel parity (venue :814-826) — the inline-btn's currentWeek reads the GLOBAL; without this the handoff carries a stale boot week */
                buildTimetable();
            }

            function nextWeek() {
                weekNav.nextWeek();
                currentWeek = weekNav.currentWeek;
                window.currentWeek = currentWeek;   /* SDD cohort-venue-replace-now T1: same channel parity */
                buildTimetable();
            }

            function selectWeek(index) {
                weekNav.selectWeek(parseInt(index, 10));
                currentWeek = weekNav.currentWeek;
                window.currentWeek = currentWeek;   /* SDD cohort-venue-replace-now T1: same channel parity */
                buildTimetable();
            }

            function goToday() {
                weekNav.jumpToToday();
                currentWeek = weekNav.currentWeek;
                window.currentWeek = currentWeek;   /* SDD cohort-venue-replace-now T1: same channel parity */
                buildTimetable();
            }

            /* ════════ GRID ════════ */

            function updateSummary() {
                const weekEvents = (eventsByCohort[selectedCohortId] || [])[currentWeek] || [];
                computeSummary(weekEvents, weekData[currentWeek].days);
                updateWeekArrows(currentWeek <= 0, currentWeek >= weekData.length - 1);
            }

            function buildTimetable() {
                /* class-modal-affordances T2: the cancel machinery's twin
                   store follows the SELECTED cohort (resolveMatches reads
                   MockData.myTimetable.eventsByWeek — the merge-twin facts
                   need the rows of the cohort the modal serves). */
                MockData.myTimetable = { eventsByWeek: eventsByCohort[selectedCohortId] || Array.from({ length: (window.weekData || []).length || 14 }, function () { return []; }) };
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
                    /* the user's hover-voice directive 2026-10-25: the
                       viewer's own classes read ME instead of the staff
                       name — SAME ownership rule the block border uses
                       (statusClassFn's isMine), so voice and border can
                       never disagree. */
                    tipLecturerFn: function (e) {
                        return (e.lecturer === MockData.currentUser.name) ? 'ME' : (e.lecturer || '—');
                    },
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
                        } else if (e.status === 'replacement') {
                            // USER 2026-10-19: the approved replacement carries the
                            // REPLACEMENT blue on this page (my-timetable's own
                            // event-replacement class) — ownership borders stay
                            // (3px mine / 0.5px others') per §10.0's two-axis rule.
                            div.classList.add(isMine ? 'event-mine-replacement' : 'event-others-replacement');
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
                /* SDD cohort-venue-replace-now T1 (my-timetable :151 / venue
                   :295 parity): write the window channel FIRST — the footer
                   button's click reads it; the gate below then decides. */
                window.currentModalEvent = event;
                openClassModal({
                    event: event,
                    dayIndex: (di !== undefined ? di : event.di),
                    days: weekData[currentWeek].days,
                    modalId: 'eventModal',
                    extraFields: [
                        { label: 'Cohort', value: event.cohort || '—' }
                    ]
                });

                /* SDD cohort-venue-replace-now T1: the venue gate family
                   (venue-timetable :315–318, byte-parity) — OWN + conflict,
                   fail-closed (display:none default). The cohort payload
                   keys the ownership at event.lecturer (baseEvent) and the
                   conflict at event.status — PLUS the page's own holiday-day
                   conflict definition (the statusClassFn's isConflict:
                   (days[event.di] && days[event.di].holiday) || status ===
                   'conflict') — the AMSE2003 (T) Dec 24 class app is
                   status-normal but sits ON the W14 holiday — the honest
                   own-conflict gate reads BOTH. */
                const replaceBtn = document.getElementById('btnReplaceNow');
                if (replaceBtn) {
                    const isMine = event.mine === true || event.lecturer === MockData.currentUser?.name;
                    const isConflict = (weekData[currentWeek].days[event.di] && weekData[currentWeek].days[event.di].holiday) || event.status === 'conflict';
                    replaceBtn.style.display = (isMine && isConflict) ? 'flex' : 'none';
                }
                /* class-modal-affordances T2: the Cancel affordance restored
                   (the legacy CohortTimetable template's wiring ported —
                   restore-first persistence). The Later path re-runs the
                   allEvents assembly then rebuilds the grid + summary. */
                CancelClass.renderButton(
                    document.querySelector('#eventModal .modal-footer'),
                    event, weekData[currentWeek].days, currentWeek,
                    function () {
                        closeModal();
                        buildTimetable();
                        updateSummary();
                    }
                );
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
            } else if (!isStudentViewer) {
                /* last-visit-dd-memory (D3): Restore (direct-sets the selects,
                   never routes through onCohortChange — the remember gate
                   stays single-path). The 2026-10-13 default rule plays
                   VERBATIM in the `else` arm (own faculty + the first taught
                   cohort; guidance when they teach nothing there). */
                const staffId = MockData.currentUser.staffId;
                const saved = staffId ? LastVisit.recall('cohortPage', staffId) : null;
                const facultySel = document.getElementById('facultySelect');
                const savedFac = saved ? facultyData.find(f => String(f.id) === String(saved.f)) : null;
                const savedCohort = savedFac ? savedFac.cohorts.find(c => String(c.id) === String(saved.c)) : null;
                if (savedFac && savedCohort) {
                    facultySel.value = String(savedFac.id);
                    populateCohorts(String(savedFac.id));
                    document.getElementById('cohortSelect').value = String(saved.c);
                    selectedCohortId = parseInt(saved.c, 10);
                    document.getElementById('weekSelect').disabled = false;
                    refreshTips();
                    buildTimetable();
                } else {
                    const fac = facultyData.find(f => String(f.id) === String(defaultFacultyId));
                    if (fac) {
                        document.getElementById('facultySelect').value = String(fac.id);
                        onFacultyChange(String(fac.id), { origin: 'programmatic' });
                    } else {
                        refreshTips();
                        showGuidance();
                    }
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
