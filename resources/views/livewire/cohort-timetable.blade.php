{{-- Livewire full-page view (SDD wire-backend-into-refactored-ui). All roles;
     students receive only their own cohort's events server-side (FR 1.2).
     Faculty/cohort selects are JS-owned and switch client-side over the
     preloaded eventsByCohort payload — no server roundtrip needed.

     cohort-venue-responsive-port (phase 1, 2026-11-17): the page root carries the
     shared `.tt-responsive` scope with pageKey "cohort", so TimetableResponsive
     (public/js/ui-common.js) owns the narrower bands — agenda below 744, two-day
     window 744–1023, three-day 1024–1279, four-day ≥1280 on a coarse pointer, and
     the existing full-week desktop only on a mouse — while the Week summary sheet
     holds the legend + the five cards below 1280 (partials/ui-tt-chrome). The
     toolbar (selects + week row + Today + Print + day strip) is ONE card in normal
     page flow. ≥1280 mouse-only stays the byte-identical desktop. The
     Select-a-Session popup is NOT part of this lane; the arrangement grid is
     explicitly excluded. --}}

@section('title', 'Cohort Timetable — Class Replacement System')

<div class="lw-page tt-responsive" data-tt-page="cohort">

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

        /* ───── cohort-venue-responsive-port (phase 1): the ONE toolbar card ─────
           The chrome's #ttSticky row carries the whole toolbar — selects row, week
           row, Today + Print row and the day strip — as ONE card in normal page
           flow at every width (prototype round 5: "the live .semester-bar is in
           flow, so this clone is too"). cfg.sticky:false sets data-tt-sticky="0",
           which keeps theme.css's sticky rule off; here the wrapper becomes the
           card and the semester-bar inside it dissolves, so ≥1280 mouse reads the
           same one-card row it has always had. */
        .tt-responsive[data-tt-page="cohort"] .tt-sticky {
            display: block;
            background: var(--color-surface);
            border: 1px solid var(--color-outline);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-sm);
            padding: 10px;
            margin-bottom: 12px;
        }
        /* Followup (2026-11-17): ≥1280 MOUSE keeps the LIVE desktop bar's exact
           geometry (baseline crs-cohort-venue-baseline, desktop 1440) — padding
           12px 16px like the shipped `.semester-bar`, and NO extra bottom margin
           (the grid wrapper's own 14px top margin is the baseline gap below the
           bar). The 10px wrapped card is the ≤1279 / coarse-pointer shape only,
           exactly the prototype's `(max-width:1279px), (any-pointer:coarse)`
           split; a wide-touch ≥1280 device keeps the 10px card and its strip. */
        @media (min-width: 1280px) and (any-pointer: fine) {
            .tt-responsive[data-tt-page="cohort"] .tt-sticky {
                padding: 12px 16px;
                margin-bottom: 0;
            }
        }
        .tt-responsive[data-tt-page="cohort"] .tt-sticky .semester-bar {
            background: transparent;
            border: 0;
            box-shadow: none;
            border-radius: 0;
            padding: 0;
            margin: 0;
            gap: 8px;
        }
        /* Below 1280 the card stacks as the live page does: selects row → full
           width week row → Today (left) + Print (right) row → day strip. */
        @media (max-width: 1279px) {
            .tt-responsive[data-tt-page="cohort"] .tt-sticky .semester-bar {
                flex-wrap: wrap;
                row-gap: 8px;
            }
            .tt-responsive[data-tt-page="cohort"] .tt-sticky .semester-bar select:not(.week-select) {
                min-width: 0;
                flex: 1 1 0;
                width: auto;
            }
            .tt-responsive[data-tt-page="cohort"] .tt-sticky .week-nav {
                flex: 1 1 100%;
                width: 100%;
            }
            .tt-responsive[data-tt-page="cohort"] .tt-sticky .today-btn {
                margin-right: auto;
            }
        }
        /* Below 375 the fourteen week labels need the full arrow row — Today wraps
           rather than clipping a date or shrinking a real touch target. */
        @media (max-width: 374px) {
            .tt-responsive[data-tt-page="cohort"] .tt-sticky .semester-bar select:not(.week-select) {
                flex: 1 1 100%;
            }
        }

        /* ───── No hover effect on a touch screen (standing rule 2026-11-16) ─────
           The chrome's hover skins are theme.css shared rules, so the neutralisers
           ride THIS page until they are promoted deliberately (the prototype's
           porting recipe: theme.css stays read-only for the port). Focus styles are
           not hover and stay exactly as they are. */
        @media (hover: none) {
            .tt-responsive[data-tt-page="cohort"] .tt-summary-btn:hover { background: var(--color-surface); }
            .tt-responsive[data-tt-page="cohort"] button.tt-next-hint:hover .tt-next-details { color: inherit; }
            .tt-responsive[data-tt-page="cohort"] .tt-sheet-foot button:hover { background: var(--color-surface-variant); border-color: var(--color-outline); }
            .tt-responsive[data-tt-page="cohort"] .select-dd-item:hover { background: transparent; }
            .tt-responsive[data-tt-page="cohort"] .week-arrow:hover { background: transparent; color: var(--color-on-surface-variant); }
            /* Followup (2026-11-17): Today keeps its BLUE resting tokens on touch —
               only the opacity/scale reset is neutralised (prototype §11: the
               shipped bullseye button stays blue). Print keeps its transparent
               resting skin. The two were wrongly merged into one rule before. */
            .tt-responsive[data-tt-page="cohort"] .print-btn:hover { background: transparent; color: var(--color-on-surface-variant); }
            .tt-responsive[data-tt-page="cohort"] .today-btn:hover { opacity: 1; transform: none; filter: none; }
            .tt-responsive[data-tt-page="cohort"] .btn-close-modal:hover,
            .tt-responsive[data-tt-page="cohort"] .btn-outline:hover { background: transparent; color: var(--color-on-surface); }
            .tt-responsive[data-tt-page="cohort"] .btn-action:hover { background: var(--color-primary); filter: none; box-shadow: none; }
            /* Followup (2026-11-17): the prototype keeps the FILLED error hover for
               .btn-danger on touch, but .btn-replace-now's shipped hover is only a
               brightness filter on its primary fill — it must not turn red. The
               two were wrongly merged into one rule before. */
            .tt-responsive[data-tt-page="cohort"] .btn-danger:hover { background: var(--color-error); color: var(--color-on-error); filter: none; }
            .tt-responsive[data-tt-page="cohort"] .btn-replace-now:hover { opacity: 1; transform: none; filter: none; }
            .tt-responsive[data-tt-page="cohort"] .timetable tbody td:hover,
            .tt-responsive[data-tt-page="cohort"] .timetable td.hour-cell:hover { background: transparent; }
            .tt-responsive[data-tt-page="cohort"] .event-block:hover { filter: none; box-shadow: none; }
            .tt-responsive[data-tt-page="cohort"] .summary-card:hover .summary-card-inner { transform: none; }
            .tt-responsive[data-tt-page="cohort"] .legend-item:hover { background: transparent; }
        }
        /* 48 × 48 on touch-capable devices only (capability, not width) — the
           SelectDd rows are this page's own popup, so the floor lives here; the
           chrome's floors are theme.css's `(any-pointer: coarse)` block. Native
           <option> values stay exempt (user ruling 2026-11-17). */
        @media (any-pointer: coarse) {
            .tt-responsive[data-tt-page="cohort"] .select-dd-item { min-height: 48px; }
            .tt-responsive[data-tt-page="cohort"] .semester-bar select { min-height: 48px; }
        }

        /* ───── The cards' ownership axis (two-axis language, §10.0) ─────
           The status classes the cards use (event-normal / -replacement /
           -pending / -conflict / -public-holiday) are the unscoped theme.css
           blocks and already carry the status colours + the 3px "yours" border;
           `.cohort-others` is the one hairline override, exactly like the
           prototype's `.preview-class.cohort-others`. */
        .tt-responsive[data-tt-page="cohort"] .tt-card.cohort-others {
            border-width: 0.5px;
            border-color: var(--color-outline-strong);
        }
    </style>

    <!-- ─── Page Header ─── -->
    @include('partials.ui-page-header', ['title' => 'Cohort Timetable', 'guideToggle' => true, 'guideModal' => true, 'summaryButton' => true])

    @include('partials.ui-guide-modal', [
        'guideSubtitle' => 'Cohort Timetable',
        'guideContentView' => 'partials.guides.cohort-timetable',
        'guideHasShortcuts' => true,
    ])

    <!-- ─── Responsive chrome + toolbar (cohort-venue-responsive-port phase 1) ───
         The WHOLE toolbar — faculty/cohort selects, week nav, Today, Print — is
         rendered to a string and handed to partials/ui-tt-chrome, which wraps it in
         its #ttSticky row together with the day strip (same pattern as My Timetable:
         a static wrap in the blade, never a runtime move). The wrapper is this
         page's ONE toolbar card, in normal page flow; the day strip renders below
         the week row inside it. ui-week-nav itself is never edited. -->
    @php
        $ttWeekNav = '<div class="semester-bar">'
            . view('partials.ui-cohort-selects', ['isStudent' => $isStudent])->render()
            . view('partials.ui-week-nav', [
                'prevOnclick' => 'prevWeek()',
                'nextOnclick' => 'nextWeek()',
                'selectId' => 'weekSelect',
                'selectOnclick' => 'selectWeek(this.value)',
                'selectTinted' => true,
                'disabled' => $pinnedCohortId === null,
                'showPrint' => true,
            ])->render()
            . '</div>';
    @endphp
    @include('partials.ui-tt-chrome', ['ttWeekNav' => $ttWeekNav, 'ttReadonly' => false])

    <!-- ─── Grid Wrapper ─── -->
    @include('partials.ui-grid-table')

    <!-- ─── Print surfaces (SDD timetable-print-v2, 2026-11-16) ───
         The context here is a COHORT CODE, not a person, so no ID prints. The centre
         and the `Faculty:` subtitle are filled by page JS (PrintSheet, design §6.2):
         the faculty/cohort selects change without a navigation, so a server-rendered
         value would go stale the moment the reader switches cohort. -->
    @include('partials.ui-print-header', [
        'printTitle' => 'Cohort Timetable',
        'printSub' => '',
        'printContext' => '',
    ])
    @include('partials.ui-print-sheet', ['printOwnership' => true])
    @include('partials.ui-print-modal')

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
        'flipCards' => true,
        'cards' => [
            ['class' => 'card-total', 'valueId' => 'sumTotal', 'label' => 'Total Classes',
                'bullets' => ['<strong class="summary-normal">Normal</strong> + <strong class="summary-replacement">blue</strong>', '<strong class="summary-pending">+N</strong> = pending', 'No <strong class="summary-excluded">red</strong> blocks'],
                'explanation' => 'All classes this cohort has this week: <span class="info-keyword">normal + replacement + pending</span>. Cancelled classes are not counted. The small <strong>+N</strong> is the pending part.',
                'detail' => [
                    'formula' => [['v' => 'normal'], ['op' => '+'], ['v' => 'replacement', 'c' => 'is-blue'], ['op' => '+'], ['v' => 'pending', 'c' => 'is-pending']],
                    'items' => [
                        ['Normal', '<span>Class runs as scheduled.</span>'],
                        ['Replacement', '<span>A replacement lecturer teaches it.</span>'],
                        ['Pending', '<span>Waiting for a PL decision.<br>Counted in the small <strong>+N</strong>.</span>'],
                        ['Cancelled', '<span>Not counted in this card.</span>'],
                    ],
                ]],
            ['class' => 'card-hours', 'valueId' => 'sumHours', 'label' => 'Teaching Hours',
                'bullets' => ['Teaching time', '<strong>30 min</strong> / slot', '<strong class="summary-pending">+N</strong> = pending'],
                'explanation' => 'Hours of normal + replacement + pending classes for this cohort this week (each slot = <strong>30 minutes</strong>). Cancelled classes are not counted. The small <strong>+N</strong> is the pending part.',
                'detail' => [
                    'formula' => [['v' => 'slots × 30 min'], ['op' => '='], ['v' => 'teaching time']],
                    'items' => [
                        ['Measured in slots', '<span>Each timetable slot is <strong>30 minutes</strong>.<br>Two slots make one hour.</span>'],
                        ['Pending', '<span>Counted in the small <strong>+N</strong> only.</span>'],
                        ['Cancelled', '<span>Excluded — no teaching happens.</span>'],
                    ],
                ]],
            ['class' => 'card-replacement', 'valueId' => 'sumMyClasses', 'label' => 'My Teaching Classes',
                'bullets' => ['Classes you teach', 'Counted per class', '<strong class="summary-pending">+N</strong> = pending'],
                'explanation' => 'Classes <span class="info-keyword">you teach</span> this week: normal + replacement + pending, each class counted on its own (Subject A (T) and Subject A (L) are two). The small <strong>+N</strong> is the pending part.',
                'detail' => [
                    'items' => [
                        ['Classes you teach', '<span>Only the classes with you as lecturer.</span>'],
                        ['Counted per class', '<span>A two-hour class counts once.</span>'],
                        ['Pending', '<span>Added in the small <strong>+N</strong> only.</span>'],
                    ],
                ]],
            ['class' => 'card-hours', 'valueId' => 'sumMyHours', 'label' => 'My Teaching Hours',
                'bullets' => ['Your teaching', '<strong>30 min</strong> / slot', '<strong class="summary-pending">+N</strong> = pending'],
                'explanation' => 'Hours of the classes <span class="info-keyword">you teach</span> this week: normal + replacement + pending (each slot = <strong>30 minutes</strong>). The small <strong>+N</strong> is the pending part.',
                'detail' => [
                    'formula' => [['v' => 'slots × 30 min'], ['op' => '='], ['v' => 'your teaching']],
                    'items' => [
                        ['Your teaching only', '<span>Other lecturers’ classes are excluded.</span>'],
                        ['Each slot is 30 min', '<span>Two slots make one hour.</span>'],
                        ['Pending', '<span>Added in the small <strong>+N</strong> only.</span>'],
                    ],
                ]],
            ['class' => 'card-conflict', 'valueId' => 'sumConflict', 'label' => 'Conflicts',
                'bullets' => ['Cancelled/clash', 'Or a holiday', '<strong class="summary-excluded">Needs make-up</strong>'],
                'explanation' => 'Classes that cannot run as scheduled — a <span class="warn-keyword">clash</span>, a cancellation, or a <strong>public holiday</strong>.',
                'detail' => [
                    'items' => [
                        ['Clash', '<span>Another class already booked in the slot.</span>'],
                        ['Cancelled', '<span>The class was called off.</span>'],
                        ['Public holiday', '<span>The whole day is closed.</span>'],
                    ],
                    'foot' => 'Each conflict needs a make-up class to be arranged.',
                ]],
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
            <button class="btn-replace-now" id="btnReplaceNow" style="display:none" onclick="goToReplacementFor(currentModalEvent?.id, currentWeek + 1, 'cohort-timetable')"><svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="17 1 21 5 17 9"/><path d="M3 11V9a4 4 0 0 1 4-4h14"/><polyline points="7 23 3 19 7 15"/><path d="M21 13v2a4 4 0 0 1-4 4H3"/></svg> Arrange Replacement</button>
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
                /* cohort-venue-responsive-port (phase 1): the chrome re-renders for the
                   guidance state too — the strip hides (no cohort), the summary opener
                   hides, and the strip's chips stop naming the previous cohort's week.
                   findNextClass answers { none: true }, so the guidance panel carries
                   no next-class hint. The guidance text is written AFTER the module's
                   own _syncTools, which would otherwise overwrite it with a count. */
                syncChromeVisibility();
                syncResponsive();
                const dayCount = document.getElementById('ttDayCount');
                if (dayCount) dayCount.textContent = 'Choose a cohort to view its timetable';
                const dayHeading = document.getElementById('ttDayHeading');
                if (dayHeading) dayHeading.textContent = '';
                /* AMENDMENT 1 §A11: this IS the funnel. Two user-reachable paths land
                   here without ever reaching buildTimetable() — the faculty switch with
                   no taught cohort, and clearing the cohort select — and neither used to
                   touch the paper, so the previous cohort's sheet stayed printable. */
                buildPrintSheet();
            }

            /* ════════ RESPONSIVE CHROME (cohort-venue-responsive-port, phase 1) ════════
               The ≤1279 bands + the ≥1280 wide-touch window read the SAME week the
               grid just drew — one set of closures handed to both the shared grid
               builder and TimetableResponsive. init() is idempotent (the cfg is
               replaced, the listeners bind once), so calling it from
               buildTimetable()/showGuidance() never double-binds.
               bands: the prototype's 744 phone edge (iPad Mini), 2/3 tablet days,
               and a FOUR-day window at ≥1280 only on a coarse pointer — a mouse
               desktop at the same width stays the existing full-week grid. The
               toolbar is in flow (sticky: false) per the prototype's round 5. */
            function syncResponsive() {
                if (!window.TimetableResponsive) return;
                TimetableResponsive.init({
                    scope: 'tt-responsive',
                    pageKey: 'cohort',
                    sticky: false,
                    bands: { phoneMax: 743, tablet2Max: 1023, desktopMin: 1280, wideTouchDays: 4 },
                    onEventClick: function (e, di) { openModal(e, di !== undefined ? di : e.di); },
                    events: function () {
                        return selectedCohortId ? ((eventsByCohort[selectedCohortId] || [])[currentWeek] || []) : [];
                    },
                    days: function () { return weekData[currentWeek].days; },
                    tipLecturerFn: tipLecturerFor,
                    statusClassFn: statusClassForCards,
                    replacementNoteFn: replacementNoteFor,
                    /* followup (2026-11-17): the Cohort prototype's holiday notice is
                       `Public Holiday · <name>` with NO repeated date line (the date
                       is already on the chip and the window header). Default
                       My/Student keep their date line. */
                    holidayNoticeDate: false,
                    findNextClass: findNextClass
                });
            }

            /* The chrome's strip and Week summary opener only belong to a page that
               has a cohort to show (prototype: `previewDays` hidden while guidance
               owns the panel). Manual inline display — the module never touches it. */
            function syncChromeVisibility() {
                const strip = document.getElementById('ttDayStrip');
                if (strip) strip.style.display = selectedCohortId ? '' : 'none';
                const sumBtn = document.getElementById('ttSummaryButton');
                if (sumBtn) sumBtn.style.display = selectedCohortId ? '' : 'none';
            }

            /* T6 parity — the next-class hint's cross-week source. Scans the SELECTED
               cohort's own payload (no second data shape): the rest of this week from
               the selected day, then the weeks after it. The hint names a class that
               will RUN — cancelled history, a pending proposal and holiday days are
               not it (the prototype's own nextClass rule). During guidance (no
               cohort) the `{ none: true }` sentinel suppresses the hint entirely —
               `Select a faculty first` must not print `No more classes this
               semester.` under it. */
            function findNextClass(fromWeek, fromDay) {
                if (!selectedCohortId) return { none: true };
                var week = parseInt(fromWeek, 10);
                var day = parseInt(fromDay, 10);
                if (isNaN(week)) week = currentWeek;
                if (isNaN(day)) day = 0;
                if (typeof currentWeek === 'number' && !isNaN(currentWeek)) week = currentWeek;
                var weeks = eventsByCohort[selectedCohortId] || [];
                for (var wk = week; wk < weekData.length; wk++) {
                    var list = weeks[wk] || [];
                    if (!list.length) continue;
                    for (var di = (wk === week ? day + 1 : 0); di < 7; di++) {
                        var dayMeta = (weekData[wk] && weekData[wk].days && weekData[wk].days[di]) || null;
                        if (!dayMeta || dayMeta.holiday) continue;
                        var best = null;
                        for (var i = 0; i < list.length; i++) {
                            var e = list[i];
                            if (!e || Number(e.di) !== di) continue;
                            if (e.status === 'cancelled' || e.status === 'conflict' || e.status === 'pending') continue;
                            if (!best || e.start < best.start
                                || (e.start === best.start && String(e.code || '') < String(best.code || ''))) {
                                best = e;
                            }
                        }
                        if (best) {
                            return { week: wk, day: di, event: best,
                                date: dayMeta.date || '', abbr: dayMeta.abbr || '' };
                        }
                    }
                }
                return null;
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
                /* summary-cards-confirmed-pending (2026-11-02): this page is the
                   one that opts into the `X+Y` split — the shared helper's
                   default stays frozen for my-timetable + student-my-timetable. */
                computeSummary(weekEvents, weekData[currentWeek].days, { splitPending: true });
                updateWeekArrows(currentWeek <= 0, currentWeek >= weekData.length - 1);
            }

            /* ═══ The paper (SDD timetable-print-v2, 2026-11-16) ═══
               The sheet is its own table, built by the SAME builder from a set derived
               here (design §1). The context is a COHORT CODE — not a person, so no ID
               prints — and it is read from the selects on every rebuild, because those
               change without a navigation (design §6.2). Ownership is the page's OWN
               test (`e.lecturer === viewer`), which is what the key's ownership line
               promises; the venue payload's `e.mine` does not exist on this page. */
            function printSelectedText(id, fallback) {
                var sel = document.getElementById(id);
                if (!sel || sel.selectedIndex < 0) return fallback;
                var opt = sel.options[sel.selectedIndex];
                var txt = opt ? (opt.textContent || '').trim() : '';
                return (txt && opt.value) ? txt : fallback;
            }

            function printIsMine(e) {
                return e.lecturer === MockData.currentUser.name;
            }

            function printBlockShape(e) {
                return {
                    cornerLeft: e.venue,
                    cornerRight: printSurname(e.lecturer),
                    centre: [e.code],
                    note: printNoteText(buildReplacementNote(e, { checkOwnership: printIsMine })),
                };
            }

            function printBlockClass(div, e, isHoliday) {
                /* a public holiday paints EVERY block conflict-red; ownership survives
                   only in the border weight (design §5.2) */
                if (isHoliday || e.status === 'conflict' || e.status === 'cancelled') div.classList.add('print-conflict');
                else if (e.status === 'replacement') div.classList.add('print-replacement');
                else div.classList.add('print-normal');
                div.classList.add(printIsMine(e) ? 'print-mine' : 'print-others');
            }

            function buildPrintSheet() {
                PrintSheet.init({
                    build: function (state) {
                        var weeks = selectedCohortId ? (eventsByCohort[selectedCohortId] || []) : [];
                        var all = state.week === null;
                        var wk = all ? currentWeek : state.week;
                        var sem = MockData.semester.weeks;
                        var weekEvents = weeks[wk] || [];
                        return {
                            events: all
                                ? printSemesterSet(weeks)
                                : printWeekSet(weekEvents, state.normal),
                            days: all ? printNormalDays() : weekData[wk].days,
                            context: printSelectedText('cohortSelect', 'Select a cohort'),
                            sub: selectedCohortId ? 'Faculty: ' + printSelectedText('facultySelect', '—') : '',
                            weekCount: all
                                ? 'Week 1 ~ ' + sem
                                : 'Week ' + (wk + 1) + ' of ' + sem,
                            weekDates: all ? '(Whole Semester)' : ' · ' + weekData[wk].range,
                            emptyTitle: all || state.normal ? 'No normal classes.' : 'No classes this week.',
                            emptyText: all
                                ? 'This cohort carries no normal class in the semester.'
                                : (selectedCohortId
                                    ? (state.normal
                                        ? 'Week ' + (wk + 1) + ' carries no normal class for this cohort.'
                                        : 'No classes scheduled for this cohort in the selected week.')
                                    : 'Select a cohort to print its timetable.'),
                        };
                    },
                    /* the dialog's week list, from THIS page's own week data (A4), so
                       every option carries the range the screen would show */
                    weeks: function () {
                        var out = [];
                        (weekData || []).forEach(function (w, i) {
                            out.push({ value: String(i), label: 'Week ' + (i + 1) + (w && w.range ? ' · ' + w.range : '') });
                        });
                        return out;
                    },
                    screenWeek: function () { return currentWeek; },
                    printShape: printBlockShape,
                    printClassFn: printBlockClass,
                    /* nothing to print until a cohort is chosen (A11) */
                    canPrint: function () { return !!selectedCohortId; },
                });
            }

            /* ═══ The shared closures — ONE set for both consumers ═══
               The grid's blocks and the responsive chrome's cards must speak exactly
               the same voice and colour ladder, so the three page closures are named
               here and handed to buildTimetableGrid() AND TimetableResponsive.init(). */
            function tipLecturerFor(e) {
                return (e.lecturer === MockData.currentUser.name) ? 'YOU' : (e.lecturer || '—');
            }

            function statusClassFor(div, e, isConflict) {
                var isMine = e.lecturer === MockData.currentUser.name;
                /* §10.0 two-axis language: colour = status (same fill for
                   everyone), border weight = ownership — own conflict/PH
                   gets the loud 3px error border; everyone else's is the
                   same red with a 0.5px hairline. (all-pages-design-parity
                   C4/C5: frozen idiom — the old e.isMine test read a field
                   no payload ever set, so everything rendered as others'.) */
                if (e.status === 'cancelled') {
                    /* cancelled-blocks-stay-visible (law ①): the cancelled
                       block rides the EXISTING conflict family — same red
                       for everyone, ownership borders kept (3px mine /
                       0.5px others'). FIRST so a cancelled-on-holiday-day
                       stays red (isConflict loses the precedence race). */
                    div.classList.add(isMine ? 'event-conflict' : 'event-public-holiday');
                } else if ((isConflict || e.status === 'conflict') && isMine) {
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
            }

            function replacementNoteFor(e) {
                return buildReplacementNote(e, {
                    checkOwnership: function (ev) { return ev.lecturer === MockData.currentUser.name; }
                });
            }

            /* The CARDS' colour ladder — the same two-axis language as the desktop grid
               (colour = status, border weight = ownership), expressed with the UNSCOPED
               block classes, because `.timetable .event-mine/.event-others` only paint
               inside the desktop table and a card outside it would render unstyled grey
               (found on the wide-touch window receipt). Ownership still rides the border:
               the class defaults are 3px, and `.cohort-others` (page CSS) drops it to the
               0.5px hairline — exactly the prototype's own pair. */
            function statusClassForCards(div, e, isConflict) {
                var isMine = e.lecturer === MockData.currentUser.name;
                if (e.status === 'cancelled') {
                    /* cancelled-blocks-stay-visible (law ①): the EXISTING conflict
                       family — mine loud 3px red, others' the red hairline. */
                    div.classList.add(isMine ? 'event-conflict' : 'event-public-holiday');
                } else if ((isConflict || e.status === 'conflict') && isMine) {
                    div.classList.add('event-conflict');
                } else if (isConflict || e.status === 'conflict') {
                    div.classList.add('event-public-holiday');
                } else if (e.status === 'replacement') {
                    /* USER 2026-10-19: the approved replacement carries the blue on
                       this page too; ownership borders stay on the axis below. */
                    div.classList.add('event-replacement');
                } else if (e.status === 'pending') {
                    div.classList.add('event-pending');
                } else {
                    div.classList.add('event-normal');
                }
                div.classList.add(isMine ? 'cohort-mine' : 'cohort-others');
            }

            function buildTimetable() {
                /* class-modal-affordances T2: the cancel machinery's twin
                   store follows the SELECTED cohort (resolveMatches reads
                   MockData.myTimetable.eventsByWeek — the merge-twin facts
                   need the rows of the cohort the modal serves). */
                MockData.myTimetable = { eventsByWeek: eventsByCohort[selectedCohortId] || Array.from({ length: (window.weekData || []).length || 14 }, function () { return []; }) };
                currentWeek = weekNav.currentWeek;
                /* cohort-venue-responsive-port (phase 1): the chrome re-renders for the
                   week that just landed — BEFORE the early returns, so an empty week
                   or a guidance exit still redraws the strip/agenda/window and never
                   leaves a stale surface from the previous cohort. */
                syncChromeVisibility();
                syncResponsive();
                if (!selectedCohortId) {
                    showGuidance();
                    /* design §3.3: this exit is BEFORE the grid build, so the paper has
                       to be rebuilt here too or it would print the previous cohort's week */
                    buildPrintSheet();
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
                    /* same reason as above — an empty week must clear the paper too */
                    buildPrintSheet();
                    return;
                }

                document.getElementById('emptyState').style.display = 'none';
                document.getElementById('summarySection').style.display = '';

                buildTimetableGrid({
                    events: weekEvents,
                    days: weekData[currentWeek].days,
                    onEventClick: function (e, di) { openModal(e, di); },
                    /* the user's hover-voice directive 2026-10-25: the
                       viewer's own classes read YOU instead of the staff
                       name (2026-11-05: was ME) — SAME ownership rule the
                       block border uses (statusClassFn's isMine), so voice
                       and border can never disagree. The closure is named
                       once (tipLecturerFor) because the responsive chrome's
                       cards must speak the exact same voice. */
                    tipLecturerFn: tipLecturerFor,
                    statusClassFn: statusClassFor,
                    replacementNoteFn: replacementNoteFor
                });
                /* cancelled-blocks-stay-visible: the filter is REMOVED (the
                   full array reaches computeSummary; consistent with the
                   unfiltered :463 site — the last-writer render inconsistency
                   dies with the tallies being cancelled-aware). */
                computeSummary(weekEvents, weekData[currentWeek].days, { splitPending: true });
                updateWeekArrows(currentWeek <= 0, currentWeek >= weekData.length - 1);
                buildPrintSheet();
            }

            /* ════════ MODAL ════════ */

            function openModal(event, di) {
                /* SDD cohort-venue-replace-now T1 (my-timetable :151 / venue
                   :295 parity): write the window channel FIRST — the footer
                   button's click reads it; the gate below then decides. */
                window.currentModalEvent = event;
                /* BMSE3153-class-info (user law 2026-10-27): the class
                   reference's OWN membership — the cohortSelect filters the
                   ON-SCREEN timetable, NOT the class's cohort list. A multi-
                   cohort class shows `RSD3(S1)G2 + RSD3(S1)G3` in BOTH G2 and
                   G3 views (the payload's cohorts list = the session's real
                   assignment), and the Total Students row carries the A + B
                   = total breakdown (studentCounts, real data). Single-
                   cohort classes keep the scope label + the plain count. */
                const isMulti = event.cohorts && event.cohorts.length > 1;
                const modalCohortValue = isMulti ? event.cohorts.join(' + ') : (event.cohort || '—');
                /* BMSE3153-class-info (user law 2026-10-27): the shared
                   totalStudentsLabel (ui-common.js) renders the per-cohort
                   `A + B = total` breakdown for multi-cohort classes. */
                const studentValue = window.totalStudentsLabel(event);
                openClassModal({
                    week: window.currentWeek + 1,
                    event: event,
                    dayIndex: (di !== undefined ? di : event.di),
                    days: weekData[currentWeek].days,
                    modalId: 'eventModal',
                    extraFields: [
                        { label: 'Cohort', value: modalCohortValue },
                        { label: 'Total Students', value: studentValue }
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
                /* venue-status-parity T9 (bug #4): the gate is the SERVED
                   exact-pair conflict fact, byte-parity with my-timetable — the
                   hand-rolled `holiday || status === 'conflict'` heuristic could
                   not see a live proposal (it offered a dead-end button on a
                   class that already HAS one) and missed the cancelled class
                   (which needs a replacement most). `occurrenceFact` is null
                   when the pair is handled or normal. The served array is
                   re-read by id+di because Livewire's marker morph can blank
                   the DOM clone's extra key. */
                const replaceBtn = document.getElementById('btnReplaceNow');
                if (replaceBtn) {
                    const isMine = event.mine === true || event.lecturer === MockData.currentUser?.name;
                    let stamped = event;
                    try {
                        ((eventsByCohort[selectedCohortId] || [])[currentWeek] || []).forEach(function (x) {
                            if (x && x.id === event.id && x.di === event.di && x.occurrenceFact) {
                                stamped = x;
                            }
                        });
                    } catch (e) { /* pre-boot: the clone is the truth */ }
                    replaceBtn.style.display = (isMine && stamped.occurrenceFact) ? 'flex' : 'none';
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
            populateWeekSelect('weekSelect', { ranges: false, selected: currentWeek,
                /* cohort-venue-responsive-port followup (2026-11-17): this page's
                   phone band ends at 743 (iPad Mini), so the compact no-year week
                   label must end there too — 744–767 print the FULL dated range,
                   exactly as the prototype's week-select contract says. The shared
                   helper's default 767 edge is untouched for My/Student. */
                mobileMax: 743 });
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
